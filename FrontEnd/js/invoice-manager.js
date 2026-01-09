class InvoiceManager {
    constructor() {
        this.api = mojoAPI; // Use the global API instance
        this.currentInvoice = null;
    }
    
    // Load invoice list
    async loadInvoices(params = {}) {
        try {
            const response = await this.api.getInvoices(params);
            
            // Update UI with invoices
            this.renderInvoiceList(response.data);
            this.renderStats(response.stats);
            
            return response;
        } catch (error) {
            console.error('Failed to load invoices:', error);
            this.showError('Failed to load invoices: ' + error.message);
            throw error;
        }
    }
    
    // Create new invoice
    async createInvoice(invoiceData) {
        try {
            const response = await this.api.createInvoice(invoiceData);
            
            this.showSuccess('Invoice created successfully!');
            return response;
        } catch (error) {
            console.error('Failed to create invoice:', error);
            this.showError('Failed to create invoice: ' + error.message);
            throw error;
        }
    }
    
    // Update invoice
    async updateInvoice(id, invoiceData) {
        try {
            const response = await this.api.request(`/api/invoices/${id}`, {
                method: 'PUT',
                body: JSON.stringify(invoiceData)
            });
            
            this.showSuccess('Invoice updated successfully!');
            return response;
        } catch (error) {
            console.error('Failed to update invoice:', error);
            this.showError('Failed to update invoice: ' + error.message);
            throw error;
        }
    }
    
    // Delete invoice
    async deleteInvoice(id) {
        if (!confirm('Are you sure you want to delete this invoice?')) {
            return;
        }
        
        try {
            const response = await this.api.request(`/api/invoices/${id}`, {
                method: 'DELETE'
            });
            
            this.showSuccess('Invoice deleted successfully!');
            return response;
        } catch (error) {
            console.error('Failed to delete invoice:', error);
            this.showError('Failed to delete invoice: ' + error.message);
            throw error;
        }
    }
    
    // Mark invoice as paid
    async markAsPaid(id, paymentData) {
        try {
            const response = await this.api.request(`/api/invoices/${id}`, {
                method: 'PATCH',
                body: JSON.stringify({
                    status: 'paid',
                    ...paymentData
                })
            });
            
            this.showSuccess('Invoice marked as paid!');
            return response;
        } catch (error) {
            console.error('Failed to mark invoice as paid:', error);
            this.showError('Failed to update invoice: ' + error.message);
            throw error;
        }
    }
    
    // Generate invoice number
    async generateInvoiceNumber() {
        try {
            const response = await this.api.request('/api/invoice-number');
            return response.invoice_no;
        } catch (error) {
            console.error('Failed to generate invoice number:', error);
            return this.generateLocalInvoiceNumber();
        }
    }
    
    // Generate local invoice number as fallback
    generateLocalInvoiceNumber() {
        const now = new Date();
        const timestamp = now.getTime();
        const random = Math.floor(Math.random() * 1000);
        return `INV-${timestamp}-${random}`;
    }
    
    // Export invoices
    async exportInvoices(format = 'csv', params = {}) {
        const query = new URLSearchParams({
            format: format,
            ...params
        }).toString();
        
        window.open(`${this.api.baseUrl}/api/invoices/export?${query}`, '_blank');
    }
    
    // Render invoice list in table
    renderInvoiceList(invoices) {
        const tableBody = document.getElementById('invoiceTableBody');
        if (!tableBody) return;
        
        tableBody.innerHTML = '';
        
        invoices.forEach(invoice => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${invoice.invoice_no}</td>
                <td>${invoice.client_name}</td>
                <td>${new Date(invoice.invoice_date).toLocaleDateString()}</td>
                <td>KES ${invoice.total.toFixed(2)}</td>
                <td>
                    <span class="badge badge-${this.getStatusClass(invoice.status)}">
                        ${invoice.status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewInvoice(${invoice.id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn btn-sm btn-success" onclick="generatePDF(${invoice.id})">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    ${invoice.status !== 'paid' ? 
                        `<button class="btn btn-sm btn-warning" onclick="markAsPaid(${invoice.id})">
                            <i class="fas fa-check"></i> Mark Paid
                        </button>` : ''
                    }
                    <button class="btn btn-sm btn-danger" onclick="deleteInvoice(${invoice.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }
    
    // Get CSS class for status badge
    getStatusClass(status) {
        const classes = {
            'draft': 'secondary',
            'pending': 'warning',
            'sent': 'info',
            'paid': 'success',
            'cancelled': 'danger',
            'overdue': 'danger'
        };
        return classes[status] || 'secondary';
    }
    
    // Show success message
    showSuccess(message) {
        // Implement your UI notification system
        alert('Success: ' + message); // Replace with toast notification
    }
    
    // Show error message
    showError(message) {
        // Implement your UI notification system
        alert('Error: ' + message); // Replace with toast notification
    }
    
    // Render statistics
    renderStats(stats) {
        document.getElementById('totalInvoices').textContent = stats.total_invoices || 0;
        document.getElementById('totalRevenue').textContent = 'KES ' + (stats.total_revenue || 0).toFixed(2);
        document.getElementById('paidAmount').textContent = 'KES ' + (stats.paid_amount || 0).toFixed(2);
        document.getElementById('pendingAmount').textContent = 'KES ' + (stats.pending_amount || 0).toFixed(2);
    }
}

// Global instance
const invoiceManager = new InvoiceManager();

// Example usage
document.addEventListener('DOMContentLoaded', function() {
    // Load invoices on page load
    invoiceManager.loadInvoices();
    
    // Generate invoice number for new invoice form
    document.getElementById('generateInvoiceNo').addEventListener('click', async function() {
        const invoiceNo = await invoiceManager.generateInvoiceNumber();
        document.getElementById('invoiceNo').value = invoiceNo;
    });
});