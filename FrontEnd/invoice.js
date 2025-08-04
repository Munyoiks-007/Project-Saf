import jsPDF from "https://esm.sh/jspdf";

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import {
  getAuth,
  signInWithEmailAndPassword,
  onAuthStateChanged,
  signOut,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyBorxcVPBtSOMp7zUZ2_4NQHmwRKPFvlog",
  authDomain: "mojoenterprise-dfb82.firebaseapp.com",
  projectId: "mojoenterprise-dfb82",
  storageBucket: "mojoenterprise-dfb82.appspot.com",
  messagingSenderId: "847292925516",
  appId: "1:847292925516:web:94be20251639fe659796e9",
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

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
  const viewBtn = document.getElementById("viewInvoicesBtn");
  const savedList = document.getElementById("savedInvoicesList");
  const loginBtn = document.getElementById("loginBtn");
  const logoutBtn = document.getElementById("logoutBtn");
  const authSection = document.getElementById("authSection");
  const salesUI = document.getElementById("salesUI");
  const saveBtn = document.getElementById("saveBtn");

  authSection.style.display = "flex";
  salesUI.style.display = "none";

  document.querySelectorAll("#authSection input").forEach((input) => {
    input.style.padding = "8px";
    input.style.width = "100%";
    input.style.border = "1px solid #ccc";
    input.style.borderRadius = "4px";
  });

  document.querySelectorAll("#authSection button").forEach((btn) => {
    btn.style.padding = "8px";
    btn.style.width = "100%";
    btn.style.cursor = "pointer";
    btn.style.borderRadius = "4px";
    btn.style.border = "none";
    btn.style.backgroundColor = "#007bff";
    btn.style.color = "white";
    btn.style.fontWeight = "bold";
  });

  let savedVisible = false;
  const randomSuffix = Math.random().toString(36).substring(2, 6).toUpperCase();
  const today = new Date();
  const todayStr = today.toISOString().slice(0, 10).replace(/-/g, "");
  invoiceNumberInput.value = `INV-${todayStr}-${randomSuffix}`;

  loginBtn.addEventListener("click", async () => {
    const email = document.getElementById("authEmail").value;
    const password = document.getElementById("authPassword").value;

    try {
      await signInWithEmailAndPassword(auth, email, password);
    } catch (err) {
      feedbackMsg.textContent = "❌ Login failed";
      feedbackMsg.style.color = "red";
      console.error(err);
    }
  });

  logoutBtn.addEventListener("click", async () => {
    await signOut(auth);
    feedbackMsg.textContent = "✅ Logged out";
    feedbackMsg.style.color = "black";
  });

  onAuthStateChanged(auth, (user) => {
    if (user) {
      authSection.style.display = "none";
      salesUI.style.display = "block";
      downloadBtn.disabled = false;
      viewBtn.disabled = false;
    } else {
      authSection.style.display = "flex";
      salesUI.style.display = "none";
      downloadBtn.disabled = true;
      viewBtn.disabled = true;
    }
  });

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

  viewBtn.addEventListener("click", async () => {
    savedVisible = !savedVisible;
    savedList.innerHTML = "";

    if (!savedVisible) {
      savedList.style.display = "none";
      return;
    }

    try {
      const res = await fetch("http://localhost:3000/api/invoices");
      const invoices = await res.json();
      invoices.forEach((inv) => {
        const li = document.createElement("li");
        li.textContent = `${inv.invoice_no} | ${inv.client_name} | KES ${inv.total}`;
        savedList.appendChild(li);
      });
      savedList.style.display = "block";
    } catch (err) {
      console.error("Failed to fetch invoices", err);
    }
  });

  saveBtn?.addEventListener("click", () => {
    downloadBtn.click();
  });
});
