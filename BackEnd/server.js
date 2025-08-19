import express from "express";
import cors from "cors";
import pg from "pg";
import path from "path";
import { fileURLToPath } from "url";
import dotenv from "dotenv";

dotenv.config();

const app = express();
const port = process.env.PORT || 3000;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Enhanced CORS configuration
const allowedOrigins = [
  process.env.FRONTEND_URL || "http://localhost:5500",
  "http://127.0.0.1:5500",
  "http://localhost:3000"
];

app.use(cors({
  origin: function (origin, callback) {
    if (!origin || allowedOrigins.includes(origin)) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  },
  methods: ["GET", "POST", "PUT", "DELETE"],
  credentials: true,
  allowedHeaders: ["Content-Type", "Authorization"]
}));

// Enhanced body parsing
app.use(express.json({ limit: "10mb" }));
app.use(express.urlencoded({ extended: true, limit: "10mb" }));

// Request logging middleware
app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.originalUrl}`);
  next();
});

// Serve static files
app.use(express.static(path.join(__dirname, "../FrontEnd"), {
  maxAge: process.env.NODE_ENV === "production" ? "1d" : "0",
  setHeaders: (res, filePath) => {
    if (path.extname(filePath) === ".html") {
      res.setHeader("Cache-Control", "no-cache, no-store, must-revalidate");
    }
  }
}));

// Database pool configuration
const pool = new pg.Pool({
  connectionString: process.env.DATABASE_URL,
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000,
  ssl: process.env.NODE_ENV === "production" ? { rejectUnauthorized: false } : false
});

// Utility functions
const validateNumber = (value, fieldName) => {
  const num = Number(value);
  if (isNaN(num)) throw new Error(`Invalid ${fieldName}: must be a number`);
  return num;
};

const toNumber = (value) => {
  if (value === null || value === undefined) return 0;
  const num = typeof value === 'string' ? parseFloat(value) : Number(value);
  return isNaN(num) ? 0 : num;
};

// Root endpoint
app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "../FrontEnd/invoice.html"));
});

// API documentation endpoint
app.get("/api", (req, res) => {
  res.json({
    status: "API is running",
    endpoints: {
      createInvoice: "POST /api/invoices",
      listInvoices: "GET /api/invoices",
      getInvoice: "GET /api/invoices/:id",
      generateNumber: "GET /api/invoice-number",
      generatePDF: "POST /api/invoices/:id/pdf"
    }
  });
});

// Generate invoice number endpoint
app.get("/api/invoice-number", (req, res) => {
  const now = new Date();
  const invNo = `INV-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}-${String(Math.floor(Math.random() * 1000)).padStart(3, '0')}`;
  res.json({ invoice_no: invNo });
});

// Health check endpoint
app.get("/api/health", async (req, res) => {
  try {
    const dbResult = await pool.query("SELECT NOW() as time");
    res.status(200).json({
      status: "healthy",
      database: {
        connected: true,
        timestamp: dbResult.rows[0].time
      },
      uptime: process.uptime(),
      memory: process.memoryUsage()
    });
  } catch (err) {
    res.status(503).json({
      status: "unhealthy",
      database: {
        connected: false,
        error: err.message
      }
    });
  }
});

// Create new invoice
app.post("/api/invoices", async (req, res) => {
  const client = await pool.connect();
  try {
    const { invoice_no, client_name, invoice_date, subtotal, tax, total, items } = req.body;

    // Validation
    if (!invoice_no || !client_name || !invoice_date) {
      return res.status(400).json({
        success: false,
        error: "Missing required fields",
        required: ["invoice_no", "client_name", "invoice_date"],
        received: req.body
      });
    }

    if (!Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ 
        success: false,
        error: "At least one item is required" 
      });
    }

    await client.query("BEGIN");

    // Insert invoice
    const invoiceResult = await client.query(
      `INSERT INTO invoices 
       (invoice_no, client_name, invoice_date, subtotal, tax, total)
       VALUES ($1, $2, $3, $4, $5, $6)
       RETURNING id`,
      [
        invoice_no,
        client_name,
        invoice_date,
        validateNumber(subtotal, "subtotal"),
        validateNumber(tax, "tax"),
        validateNumber(total, "total")
      ]
    );

    // Insert items
    for (const item of items) {
      await client.query(
        `INSERT INTO invoice_items 
         (invoice_id, item, description, quantity, unit_price, total)
         VALUES ($1, $2, $3, $4, $5, $6)`,
        [
          invoiceResult.rows[0].id,
          item.item?.substring(0, 100) || "Unspecified Item",
          item.description?.substring(0, 500) || "",
          validateNumber(item.quantity, "quantity"),
          validateNumber(item.unit_price, "unit_price"),
          validateNumber(item.total, "item total")
        ]
      );
    }

    await client.query("COMMIT");

    res.status(201).json({
      success: true,
      invoiceId: invoiceResult.rows[0].id,
      invoiceNo: invoice_no
    });

  } catch (err) {
    await client.query("ROLLBACK");
    const statusCode = err.message.includes("Invalid") ? 400 : 500;
    res.status(statusCode).json({
      success: false,
      error: err.message,
      ...(process.env.NODE_ENV === "development" && { stack: err.stack })
    });
  } finally {
    client.release();
  }
});

// Get invoices with pagination
app.get("/api/invoices", async (req, res) => {
  const client = await pool.connect();
  try {
    const { page = 1, limit = 20, client_name, date_from, date_to } = req.query;
    const offset = (Math.max(1, page) - 1) * limit;

    let query = `
      SELECT 
        id, invoice_no, client_name, invoice_date,
        subtotal::float, tax::float, total::float,
        created_at, updated_at
      FROM invoices
      WHERE 1=1
    `;

    const params = [];
    let paramCount = 1;

    if (client_name) {
      query += ` AND client_name ILIKE $${paramCount++}`;
      params.push(`%${client_name}%`);
    }

    if (date_from) {
      query += ` AND invoice_date >= $${paramCount++}`;
      params.push(date_from);
    }

    if (date_to) {
      query += ` AND invoice_date <= $${paramCount++}`;
      params.push(date_to);
    }

    query += `
      ORDER BY created_at DESC
      LIMIT $${paramCount++} OFFSET $${paramCount++}
    `;
    params.push(limit, offset);

    // Get paginated results
    const invoices = await client.query(query, params);

    // Get total count
    const countQuery = query
      .replace(/SELECT.*?FROM/s, "SELECT COUNT(*) FROM")
      .replace(/ORDER BY.*$/s, "");
    const total = await client.query(countQuery, params.slice(0, -2));

    res.json({
      success: true,
      data: invoices.rows.map(invoice => ({
        ...invoice,
        subtotal: toNumber(invoice.subtotal),
        tax: toNumber(invoice.tax),
        total: toNumber(invoice.total)
      })),
      pagination: {
        total: parseInt(total.rows[0].count),
        page: parseInt(page),
        limit: parseInt(limit),
        totalPages: Math.ceil(total.rows[0].count / limit)
      }
    });

  } catch (err) {
    res.status(500).json({
      success: false,
      error: "Failed to fetch invoices",
      ...(process.env.NODE_ENV === "development" && { details: err.message })
    });
  } finally {
    client.release();
  }
});

// Get single invoice
app.get("/api/invoices/:id", async (req, res) => {
  const client = await pool.connect();
  try {
    const { id } = req.params;

    const invoice = await client.query(
      `SELECT * FROM invoices WHERE id = $1`,
      [id]
    );

    if (invoice.rows.length === 0) {
      return res.status(404).json({ 
        success: false,
        error: "Invoice not found" 
      });
    }

    const items = await client.query(
      `SELECT 
        id, item, description, 
        quantity::float, unit_price::float, total::float
       FROM invoice_items 
       WHERE invoice_id = $1`,
      [id]
    );

    res.json({
      success: true,
      data: {
        ...invoice.rows[0],
        items: items.rows.map(item => ({
          ...item,
          quantity: toNumber(item.quantity),
          unit_price: toNumber(item.unit_price),
          total: toNumber(item.total)
        }))
      }
    });

  } catch (err) {
    res.status(500).json({
      success: false,
      error: "Failed to fetch invoice",
      ...(process.env.NODE_ENV === "development" && { details: err.message })
    });
  } finally {
    client.release();
  }
});

// Generate PDF endpoint
app.post("/api/invoices/:id/pdf", async (req, res) => {
  const client = await pool.connect();
  try {
    const { id } = req.params;

    // Get invoice data
    const invoice = await client.query(
      `SELECT * FROM invoices WHERE id = $1`,
      [id]
    );

    if (invoice.rows.length === 0) {
      return res.status(404).json({ 
        success: false,
        error: "Invoice not found" 
      });
    }

    const items = await client.query(
      `SELECT * FROM invoice_items WHERE invoice_id = $1`,
      [id]
    );

    // In a real implementation, generate PDF here
    res.json({
      success: true,
      message: "PDF would be generated here",
      data: {
        ...invoice.rows[0],
        items: items.rows
      }
    });

  } catch (err) {
    res.status(500).json({
      success: false,
      error: "Failed to generate PDF",
      ...(process.env.NODE_ENV === "development" && { details: err.message })
    });
  } finally {
    client.release();
  }
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(`[${new Date().toISOString()}] Error:`, err);
  res.status(500).json({
    success: false,
    error: "Internal server error",
    ...(process.env.NODE_ENV === "development" && { details: err.message })
  });
});

// Add security headers middleware
app.use((req, res, next) => {
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Strict-Transport-Security', 'max-age=31536000');
  next();
});

// Add session invalidation if using sessions
app.post("/api/logout", (req, res) => {
  // If using session cookies
  res.clearCookie('sessionId');
  res.clearCookie('authToken');
  
  res.json({ 
    success: true, 
    message: "Logged out successfully",
    redirectUrl: "/logout.html" 
  });
});

// Add this to your server.js routes

// ▶️ Logout endpoint
app.post("/api/logout", (req, res) => {
  // Clear any server-side sessions if you have them
  // For JWT tokens, the frontend should handle token removal
  
  res.json({ 
    success: true, 
    message: "Logged out successfully",
    redirectUrl: "/logout.html" 
  });
});

// ▶️ Serve logout page
app.get("/logout.html", (req, res) => {
  res.sendFile(path.join(__dirname, "../FrontEnd/logout.html"));
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ 
    success: false,
    error: "Endpoint not found" 
  });
});

// Graceful shutdown
const shutdown = async () => {
  console.log("Shutting down gracefully...");
  await pool.end();
  process.exit(0);
};

process.on("SIGTERM", shutdown);
process.on("SIGINT", shutdown);

// Start server
app.listen(port, () => {
  console.log(`
  🚀 Server ready at http://localhost:${port}
  📅 ${new Date().toLocaleString()}
  ⚙️ Environment: ${process.env.NODE_ENV || "development"}
  🌐 Allowed Origins: ${allowedOrigins.join(", ")}
  `);
});