import jsPDF from "https://esm.sh/jspdf";

window.addEventListener("DOMContentLoaded", () => {
  const addItemBtn = document.getElementById("addItemBtn");
  const itemList = document.getElementById("itemList");
  const downloadBtn = document.getElementById("downloadBtn");
  const spinner = document.getElementById("spinner");
  const feedbackMsg = document.getElementById("feedbackMsg");
  const invoiceNumberInput = document.getElementById("invoiceNumber");
  const taxField = document.getElementById("taxAmount");
  const subtotalField = document.getElementById("subtotal");
  const totalField = document.getElementById("total");

  const randomSuffix = Math.random().toString(36).substring(2, 6).toUpperCase();
  const today = new Date();
  const todayStr = today.toISOString().slice(0, 10).replace(/-/g, "");
  invoiceNumberInput.value = `INV-${todayStr}-${randomSuffix}`;

  addItemBtn.addEventListener("click", () => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td><input type="text" placeholder="Item" /></td>
      <td><input type="text" placeholder="Description" /></td>
      <td><input type="number" min="1" value="1" /></td>
      <td><input type="number" min="0" step="0.01" value="0.00" /></td>
      <td class="item-total">0.00</td>
      <td><button class="removeBtn">🗑️</button></td>
    `;
    itemList.appendChild(row);
    updateTotals();
  });

  itemList.addEventListener("input", updateTotals);
  itemList.addEventListener("click", (e) => {
    if (e.target.classList.contains("removeBtn")) {
      e.target.closest("tr").remove();
      updateTotals();
    }
  });

  taxField.addEventListener("input", updateTotals);

  function updateTotals() {
    let subtotal = 0;
    document.querySelectorAll("#itemList tr").forEach((row) => {
      const qty = parseFloat(row.cells[2].querySelector("input").value) || 0;
      const price = parseFloat(row.cells[3].querySelector("input").value) || 0;
      const total = qty * price;
      row.cells[4].textContent = total.toFixed(2);
      subtotal += total;
    });
    const tax = parseFloat(taxField.value) || 0;
    subtotalField.textContent = subtotal.toFixed(2);
    totalField.textContent = (subtotal + tax).toFixed(2);
  }

  downloadBtn.addEventListener("click", async () => {
    feedbackMsg.textContent = "";
    spinner.style.display = "block";

    const name = document.getElementById("clientName").value.trim();
    const date = document.getElementById("invoiceDate").value;
    const number = document.getElementById("invoiceNumber").value.trim();

    if (!name || !date || !number) {
      feedbackMsg.textContent = "⚠️ Please fill in Client Name, Date, and Invoice No.";
      feedbackMsg.style.color = "red";
      spinner.style.display = "none";
      return;
    }

    try {
      const doc = new jsPDF();
      const logo = new Image();
      logo.src = "logo.png";

      await new Promise((resolve) => {
        logo.onload = resolve;
      });

      doc.addImage(logo, "PNG", 10, 10, 30, 30);
      doc.setFontSize(12);
      doc.text("Reliability in Electrical Services", 10, 45);

      doc.setFontSize(16);
      doc.setFont(undefined, "bold");
      doc.text("Mojo Electrical Enterprise", 50, 18);
      doc.setLineWidth(0.5);
      doc.line(50, 20, 180, 20);

      doc.setFontSize(12);
      doc.setFont(undefined, "normal");
      doc.text("P.O. Box 98664 - 80100, Mombasa", 50, 26);
      doc.text("Phone: +254 721 856 011 / 0731 120 072", 50, 32);
      doc.text("Email: gathucimoses@gmail.com", 50, 38);

      doc.setFontSize(14);
      doc.setFont(undefined, "bold");
      doc.text("Sales Invoice", 10, 60);
      doc.line(10, 62, 60, 62);

      doc.setFontSize(12);
      doc.setFont(undefined, "normal");
      doc.text(`Client: ${name}`, 10, 72);
      doc.text(`Date: ${date}`, 10, 80);
      doc.text(`Invoice No: ${number}`, 10, 88);

      let y = 100;
      doc.setFont(undefined, "bold");
      doc.text("Items:", 10, y);
      y += 10;
      doc.setFont(undefined, "normal");

      document.querySelectorAll("#itemList tr").forEach((row, i) => {
        const item = row.cells[0].querySelector("input").value;
        const desc = row.cells[1].querySelector("input").value;
        const qty = row.cells[2].querySelector("input").value;
        const price = row.cells[3].querySelector("input").value;
        const total = row.cells[4].textContent;
        const itemText = `${i + 1}. ${item} | ${desc} | Qty: ${qty} | Price: ${price} | Total: ${total}`;
        doc.text(itemText, 10, y);
        y += 10;
      });

      y += 5;
      const subtotal = subtotalField.textContent;
      const tax = parseFloat(taxField.value) || 0;
      const total = totalField.textContent;

      doc.text(`Subtotal: KES ${subtotal}`, 10, y);
      y += 8;
      doc.text(`Tax: KES ${tax.toFixed(2)}`, 10, y);
      y += 8;
      doc.text(`Total (After Tax): KES ${total}`, 10, y);

      doc.save(`Invoice_${number}.pdf`);

      feedbackMsg.textContent = "✅ Invoice downloaded successfully!";
      feedbackMsg.style.color = "green";
    } catch (err) {
      console.error(err);
      feedbackMsg.textContent = "❌ Failed to download invoice.";
      feedbackMsg.style.color = "red";
    } finally {
      spinner.style.display = "none";
    }
  });
});
