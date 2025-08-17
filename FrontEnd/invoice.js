document.addEventListener('DOMContentLoaded', function() {
  // Configuration
  const API_BASE_URL = 'http://localhost:3000/api';
  const AUTH_TOKEN = localStorage.getItem('authToken') || '';
  const COMPANY_LOGO_URL = 'logo.png';

  // DOM Elements
  const elements = {
    form: document.getElementById('invoice-form'),
    addItemBtn: document.getElementById('add-item'),
    downloadBtn: document.getElementById('download-pdf'),
    viewSavedBtn: document.getElementById('view-saved'),
    logoutBtn: document.getElementById('logout'),
    invoiceNoInput: document.getElementById('invoiceNo'),
    invoiceDateInput: document.getElementById('invoiceDate'),
    clientNameInput: document.getElementById('clientName'),
    taxRateInput: document.getElementById('taxRate'),
    itemsContainer: document.getElementById('items'),
    savedList: document.getElementById('saved-invoices'),
    subtotalDisplay: document.getElementById('subtotal-display'),
    taxDisplay: document.getElementById('tax-display'),
    totalDisplay: document.getElementById('total-display'),
    companyLogo: document.getElementById('company-logo')
  };

  // Initialize the application
  function init() {
    checkAuth();
    setupEventListeners();
    initializeForm();
    loadCompanyLogo();
  }

  // Check authentication
 // Check authentication - Modified version
function checkAuth() {
  // Skip auth check if we're already on the login page
  if (window.location.pathname.includes('login.html')) {
    return;
  }

  // If no token but we're trying to access invoice.html
  if (!AUTH_TOKEN && window.location.pathname.includes('invoice.html')) {
    // Show a login modal instead of redirecting
    showLoginModal();
    return;
  }

  // If no token and not on login page, redirect to login
  if (!AUTH_TOKEN) {
    window.location.href = 'login.html';
  }
}

// Add this function to show a login modal
function showLoginModal() {
  const modal = document.createElement('div');
  modal.className = 'login-modal';
  modal.innerHTML = `
    <div class="modal-content">
      <h3>Login Required</h3>
      <p>Please login to access invoices</p>
      <div class="modal-buttons">
        <button id="go-to-login">Go to Login</button>
        <button id="cancel-login">Cancel</button>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  document.body.style.overflow = 'hidden';

  // Add event listeners
  document.getElementById('go-to-login').addEventListener('click', () => {
    window.location.href = 'login.html';
  });

  document.getElementById('cancel-login').addEventListener('click', () => {
    document.body.removeChild(modal);
    document.body.style.overflow = '';
  });
}

  // Setup all event listeners
  function setupEventListeners() {
    if (!elements.form) return;

    // Form submission
    elements.form.addEventListener('submit', handleFormSubmit);

    // Add item button
    elements.addItemBtn?.addEventListener('click', addNewItemRow);

    // Remove item delegation
    elements.itemsContainer?.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove-item')) {
        e.target.closest('.item').remove();
        calculateTotals();
      }
    });

    // Input changes for real-time calculation
    elements.form.addEventListener('input', debounce(calculateTotals, 300));

    // View saved invoices
    elements.viewSavedBtn?.addEventListener('click', loadSavedInvoices);

    // PDF download
    elements.downloadBtn?.addEventListener('click', handlePdfAction);

    // Logout
    elements.logoutBtn?.addEventListener('click', logout);
  }

  // Initialize form with default values
  function initializeForm() {
    // Set current date
    const today = new Date().toISOString().split('T')[0];
    elements.invoiceDateInput.value = today;
    
    // Generate invoice number
    fetchInvoiceNumber();
    
    // Add first item row
    addNewItemRow();
  }

  // Load company logo with fallback
  function loadCompanyLogo() {
    if (!elements.companyLogo) return;
    
    elements.companyLogo.src = COMPANY_LOGO_URL;
    elements.companyLogo.onerror = () => {
      elements.companyLogo.style.display = 'none';
    };
  }

  // Fetch invoice number from API
  async function fetchInvoiceNumber() {
    try {
      const response = await fetch(`${API_BASE_URL}/invoice-number`, {
        headers: { 
          'Authorization': AUTH_TOKEN,
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) throw new Error('Failed to generate invoice number');
      
      const { invoice_no } = await response.json();
      elements.invoiceNoInput.value = invoice_no;
    } catch (error) {
      console.error('Error generating invoice number:', error);
      generateInvoiceNumber(); // Fallback to client-side
    }
  }

  // Client-side invoice number generation
  function generateInvoiceNumber() {
    const now = new Date();
    const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    elements.invoiceNoInput.value = 
      `INV-${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}-${randomNum}`;
  }

  // Add new item row to the form
  function addNewItemRow() {
    const itemDiv = document.createElement('div');
    itemDiv.className = 'item';
    itemDiv.innerHTML = `
      <input type="text" name="item[]" placeholder="Item name" required maxlength="100">
      <input type="text" name="description[]" placeholder="Description" maxlength="500">
      <input type="number" name="quantity[]" placeholder="Qty" min="0.01" step="0.01" required>
      <input type="number" name="price[]" placeholder="Unit price" min="0" step="0.01" required>
      <button type="button" class="remove-item">Remove</button>
    `;
    elements.itemsContainer.appendChild(itemDiv);
  }

  // Calculate and display totals
  function calculateTotals() {
    try {
      const items = Array.from(elements.itemsContainer.querySelectorAll('.item'));
      
      const subtotal = items.reduce((sum, item) => {
        const qty = parseFloat(item.querySelector('[name="quantity[]"]').value) || 0;
        const price = parseFloat(item.querySelector('[name="price[]"]').value) || 0;
        return sum + (qty * price);
      }, 0);

      const taxRate = parseFloat(elements.taxRateInput.value) || 0;
      const tax = subtotal * (taxRate / 100);
      const total = subtotal + tax;

      // Update display
      elements.subtotalDisplay.textContent = `Subtotal: KES ${subtotal.toFixed(2)}`;
      elements.taxDisplay.textContent = `Tax: KES ${tax.toFixed(2)}`;
      elements.totalDisplay.textContent = `Total: KES ${total.toFixed(2)}`;

      return { subtotal, tax, total };
    } catch (error) {
      console.error('Calculation error:', error);
      return { subtotal: 0, tax: 0, total: 0 };
    }
  }

  // Prepare form data for API
  function prepareInvoiceData() {
    const { subtotal, tax, total } = calculateTotals();
    
    return {
      invoice_no: elements.invoiceNoInput.value,
      client_name: elements.clientNameInput.value.trim(),
      invoice_date: elements.invoiceDateInput.value,
      subtotal,
      tax,
      total,
      items: Array.from(elements.itemsContainer.querySelectorAll('.item')).map(item => ({
        item: item.querySelector('[name="item[]"]').value.trim() || 'Unspecified Item',
        description: item.querySelector('[name="description[]"]').value.trim(),
        quantity: parseFloat(item.querySelector('[name="quantity[]"]').value) || 0,
        unit_price: parseFloat(item.querySelector('[name="price[]"]').value) || 0,
        total: (parseFloat(item.querySelector('[name="quantity[]"]').value) || 0) * 
               (parseFloat(item.querySelector('[name="price[]"]').value) || 0)
      }))
    };
  }

  // Handle form submission
  async function handleFormSubmit(e) {
    e.preventDefault();
    const submitBtn = elements.form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    try {
      const payload = prepareInvoiceData();
      
      // Validate required fields
      if (!payload.client_name) throw new Error('Client name is required');
      if (!payload.invoice_date) throw new Error('Invoice date is required');
      if (payload.items.length === 0) throw new Error('At least one item is required');

      const response = await fetchWithErrorHandling(`${API_BASE_URL}/invoices`, {
        method: 'POST',
        body: JSON.stringify(payload)
      });

      showNotification('✅ Invoice saved successfully!');
      resetForm();
    } catch (error) {
      console.error('Invoice save error:', error);
      showNotification(`❌ ${error.message}`, 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalBtnText;
    }
  }

  // Fetch wrapper with error handling
  async function fetchWithErrorHandling(url, options = {}) {
    try {
      const response = await fetch(url, {
        ...options,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': AUTH_TOKEN,
          ...options.headers
        }
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `Request failed with status ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Load saved invoices from API
  async function loadSavedInvoices() {
    if (!elements.viewSavedBtn || !elements.savedList) return;

    const originalBtnText = elements.viewSavedBtn.textContent;
    elements.viewSavedBtn.disabled = true;
    elements.viewSavedBtn.textContent = 'Loading...';
    elements.savedList.innerHTML = '<li>Loading invoices...</li>';
    elements.savedList.style.display = 'block';

    try {
      const { data } = await fetchWithErrorHandling(`${API_BASE_URL}/invoices`);
      
      elements.savedList.innerHTML = data.length > 0 
        ? data.map(invoice => `
            <li class="invoice-item">
              <div class="invoice-info">
                <strong>${invoice.invoice_no}</strong>
                <span>${invoice.client_name}</span>
                <span>KES ${invoice.total.toFixed(2)}</span>
                <span>${new Date(invoice.invoice_date).toLocaleDateString()}</span>
              </div>
              <div class="invoice-actions">
                <button class="view-btn" data-id="${invoice.id}">View</button>
                <button class="pdf-btn" data-id="${invoice.id}">PDF</button>
              </div>
            </li>
          `).join('')
        : '<li class="no-invoices">No invoices found</li>';

      // Add event listeners to buttons
      document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => viewInvoiceDetail(btn.dataset.id));
      });
      
      document.querySelectorAll('.pdf-btn').forEach(btn => {
        btn.addEventListener('click', () => downloadInvoicePdf(btn.dataset.id));
      });

    } catch (error) {
      elements.savedList.innerHTML = `
        <li class="error-message">
          <span>⚠️ Failed to load invoices</span>
          <small>${error.message}</small>
        </li>
      `;
    } finally {
      elements.viewSavedBtn.disabled = false;
      elements.viewSavedBtn.textContent = originalBtnText;
    }
  }

  // View single invoice detail
  async function viewInvoiceDetail(invoiceId) {
    try {
      const { data } = await fetchWithErrorHandling(`${API_BASE_URL}/invoices/${invoiceId}`);
      
      // Populate form with invoice data
      elements.invoiceNoInput.value = data.invoice_no;
      elements.clientNameInput.value = data.client_name;
      elements.invoiceDateInput.value = data.invoice_date;
      elements.taxRateInput.value = ((data.tax / data.subtotal) * 100).toFixed(2);
      
      // Clear and repopulate items
      elements.itemsContainer.innerHTML = '';
      data.items.forEach(item => {
        addNewItemRow();
        const lastItem = elements.itemsContainer.lastElementChild;
        lastItem.querySelector('[name="item[]"]').value = item.item;
        lastItem.querySelector('[name="description[]"]').value = item.description;
        lastItem.querySelector('[name="quantity[]"]').value = item.quantity;
        lastItem.querySelector('[name="price[]"]').value = item.unit_price;
      });
      
      calculateTotals();
      showNotification('Invoice loaded successfully');
      
      // Scroll to form
      elements.form.scrollIntoView({ behavior: 'smooth' });
    } catch (error) {
      showNotification(`Failed to load invoice: ${error.message}`, 'error');
    }
  }

  // Handle PDF action (generate or download)
  async function handlePdfAction() {
    try {
      const payload = prepareInvoiceData();
      await generatePdf(payload);
    } catch (error) {
      console.error('PDF action failed:', error);
      showNotification('Failed to generate PDF', 'error');
    }
  }

  // Generate PDF from data
  async function generatePdf(invoiceData) {
    if (!window.jspdf) {
      throw new Error('PDF library not loaded');
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add logo if available
    try {
      const logoResponse = await fetch(COMPANY_LOGO_URL);
      if (logoResponse.ok) {
        const logoBlob = await logoResponse.blob();
        const logoUrl = URL.createObjectURL(logoBlob);
        const logoImg = await loadImage(logoUrl);
        doc.addImage(logoImg, 'PNG', 14, 10, 30, 30);
        URL.revokeObjectURL(logoUrl);
      }
    } catch (e) {
      console.log('Could not load logo:', e);
    }

    // Company info
    doc.setFontSize(16).setFont('helvetica', 'bold')
       .text('Mojo Electrical Enterprise', 50, 20);
    doc.setFontSize(10).setFont('helvetica', 'normal')
       .text('P.O. Box 98664 - 80100, Mombasa', 50, 28)
       .text('Phone: +254 721 856 011 / 0731 120 072', 50, 36)
       .text('Email: gathucimoses@gmail.com', 50, 44);

    // Invoice info
    doc.setFontSize(12)
       .text(`Invoice #: ${invoiceData.invoice_no}`, 14, 60)
       .text(`Client: ${invoiceData.client_name}`, 14, 70)
       .text(`Date: ${invoiceData.invoice_date}`, 14, 80);

    // Items table
    doc.autoTable({
      head: [['Item', 'Description', 'Qty', 'Unit Price', 'Total']],
      body: invoiceData.items.map(item => [
        item.item.substring(0, 30),
        item.description.substring(0, 50),
        item.quantity,
        `KES ${item.unit_price.toFixed(2)}`,
        `KES ${item.total.toFixed(2)}`
      ]),
      startY: 90,
      headStyles: { fillColor: [41, 128, 185] },
      styles: { overflow: 'linebreak' }
    });

    // Totals
    const finalY = doc.lastAutoTable.finalY + 15;
    doc.text(`Subtotal: KES ${invoiceData.subtotal.toFixed(2)}`, 150, finalY, { align: 'right' });
    doc.text(`Tax: KES ${invoiceData.tax.toFixed(2)}`, 150, finalY + 8, { align: 'right' });
    doc.text(`Total: KES ${invoiceData.total.toFixed(2)}`, 150, finalY + 16, { align: 'right' });

    // Save PDF
    doc.save(`Invoice_${invoiceData.invoice_no}.pdf`);
    showNotification('PDF downloaded successfully');
  }

  // Download existing invoice as PDF
  async function downloadInvoicePdf(invoiceId) {
    try {
      const response = await fetch(`${API_BASE_URL}/invoices/${invoiceId}/pdf`, {
        headers: { 'Authorization': AUTH_TOKEN }
      });
      
      if (!response.ok) throw new Error('Failed to generate PDF');
      
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Invoice_${invoiceId}.pdf`;
      document.body.appendChild(a);
      a.click();
      setTimeout(() => {
        URL.revokeObjectURL(url);
        a.remove();
      }, 100);
    } catch (error) {
      console.error('PDF download failed:', error);
      showNotification('Failed to download PDF. Try generating it instead.', 'error');
    }
  }

  // Reset form to initial state
  function resetForm() {
    elements.form.reset();
    elements.itemsContainer.innerHTML = '';
    addNewItemRow();
    initializeForm();
  }

  // Load image helper for PDF generation
  function loadImage(url) {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = url;
    });
  }

  // Show notification to user
  function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
      <span>${message}</span>
      <span class="notification-close">&times;</span>
    `;
    
    notification.querySelector('.notification-close').addEventListener('click', () => {
      notification.remove();
    });
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.classList.add('fade-out');
      setTimeout(() => notification.remove(), 300);
    }, 5000);
  }

  // Debounce function for performance
  function debounce(func, wait) {
    let timeout;
    return function() {
      const context = this, args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  }

  // Logout user
  function logout() {
    localStorage.removeItem('authToken');
    window.location.href = 'login.html';
  }

  // Start the application
  init();
});