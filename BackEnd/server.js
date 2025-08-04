import express from "express";
import cors from "cors";
import { Pool } from "pg";
import dotenv from "dotenv";

dotenv.config();

const app = express();
const port = process.env.PORT || 4000;

app.use(cors());
app.use(express.json());

const pool = new Pool({
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
      items
    } = req.body;

    await client.query("BEGIN");

    const result = await client.query(
      `INSERT INTO invoices (invoice_no, client_name, invoice_date, subtotal, tax, total)
       VALUES ($1, $2, $3, $4, $5, $6) RETURNING id`,
      [invoice_no, client_name, invoice_date, subtotal, tax, total]
    );

    const invoiceId = result.rows[0].id;

    for (const item of items) {
      await client.query(
        `INSERT INTO invoice_items (invoice_id, item, description, quantity, unit_price, total)
         VALUES ($1, $2, $3, $4, $5, $6)`,
        [
          invoiceId,
          item.name,
          item.description,
          item.quantity,
          item.unit_price,
          item.total
        ]
      );
    }

    await client.query("COMMIT");
    res.status(201).json({ message: "Invoice saved successfully" });
  } catch (err) {
    await client.query("ROLLBACK");
    console.error(err);
    res.status(500).json({ error: "Server error" });
  } finally {
    client.release();
  }
});

app.listen(port, () => {
  console.log(`Server running on port ${port}`);
});
