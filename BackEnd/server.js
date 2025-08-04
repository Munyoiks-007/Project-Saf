// server.js
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

app.use(cors());
app.use(express.json());

// Serve frontend
app.use(express.static(path.join(__dirname, "../FrontEnd")));

app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "../FrontEnd/invoice.html"));
});

// PostgreSQL Pool Setup
const pool = new pg.Pool({
  connectionString: process.env.DATABASE_URL,
});

app.post("/api/invoices", async (req, res) => {
  const client = await pool.connect();
  try {
    const {
      invoice_no,
      client_name,
      invoice_date,
      subtotal,
      tax,
      total,
      items,
    } = req.body;

    await client.query("BEGIN");

    const insertInvoice = `
      INSERT INTO invoices (invoice_no, client_name, invoice_date, subtotal, tax, total)
      VALUES ($1, $2, $3, $4, $5, $6)
      RETURNING id
    `;
    const invoiceResult = await client.query(insertInvoice, [
      invoice_no,
      client_name,
      invoice_date,
      subtotal,
      tax,
      total,
    ]);

    const invoiceId = invoiceResult.rows[0].id;

    const insertItem = `
      INSERT INTO invoice_items (invoice_id, item, description, quantity, unit_price, total)
      VALUES ($1, $2, $3, $4, $5, $6)
    `;

    for (const item of items) {
      await client.query(insertItem, [
        invoiceId,
        item.item,
        item.description,
        item.quantity,
        item.unit_price,
        item.total,
      ]);
    }

    await client.query("COMMIT");
    res.status(201).json({ message: "Invoice saved." });
  } catch (err) {
    await client.query("ROLLBACK");
    console.error("Insert failed:", err);
    res.status(500).json({ error: "Failed to save invoice." });
  } finally {
    client.release();
  }
});

// Fetch all saved invoices with their items
app.get("/api/invoices", async (req, res) => {
  const client = await pool.connect();
  try {
    const invoiceQuery = `SELECT * FROM invoices ORDER BY created_at DESC`;
    const itemQuery = `SELECT * FROM invoice_items`;

    const [invoiceResult, itemResult] = await Promise.all([
      client.query(invoiceQuery),
      client.query(itemQuery),
    ]);

    // Group items by invoice_id
    const itemsByInvoice = {};
    itemResult.rows.forEach((item) => {
      if (!itemsByInvoice[item.invoice_id]) {
        itemsByInvoice[item.invoice_id] = [];
      }
      itemsByInvoice[item.invoice_id].push(item);
    });

    // Combine invoice with its items
    const invoices = invoiceResult.rows.map((inv) => ({
      ...inv,
      items: itemsByInvoice[inv.id] || [],
    }));

    res.json(invoices);
  } catch (err) {
    console.error("Fetch error:", err);
    res.status(500).json({ error: "Failed to fetch invoices." });
  } finally {
    client.release();
  }
});

app.listen(port, () => {
  console.log(`✅ Server running at http://localhost:${port}`);
});
app.get("/api/invoices", async (req, res) => {
  try {
    const invoicesRes = await pool.query("SELECT * FROM invoices ORDER BY created_at DESC");
    const invoices = invoicesRes.rows;

    // Fetch associated items for each invoice
    const detailedInvoices = await Promise.all(
      invoices.map(async (invoice) => {
        const itemsRes = await pool.query(
          "SELECT * FROM invoice_items WHERE invoice_id = $1",
          [invoice.id]
        );
        return { ...invoice, items: itemsRes.rows };
      })
    );

    res.json(detailedInvoices);
  } catch (err) {
    console.error("Error fetching invoices:", err);
    res.status(500).json({ error: "Internal server error" });
  }
});
