class MojoAPI {
    constructor(baseUrl = '/mojo') {
        this.baseUrl = baseUrl;
        this.token = localStorage.getItem('mojo_token');
    }
    
    setToken(token) {
        this.token = token;
        localStorage.setItem('mojo_token', token);
    }
    
    clearToken() {
        this.token = null;
        localStorage.removeItem('mojo_token');
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        try {
            const response = await fetch(url, {
                ...options,
                headers
            });
            
            if (response.status === 401) {
                // Token expired, redirect to login
                window.location.href = '/login.html';
                throw new Error('Authentication required');
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            return data;
            
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    // Authentication
    async register(data) {
        return this.request('/api/auth?action=register', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async login(username, password) {
        const result = await this.request('/api/auth?action=login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
        
        if (result.success && result.token) {
            this.setToken(result.token);
        }
        
        return result;
    }
    
    async logout() {
        const result = await this.request('/api/auth?action=logout', {
            method: 'POST'
        });
        
        this.clearToken();
        return result;
    }
    
    async checkAuth() {
        return this.request('/api/auth?action=check');
    }
    
    // Invoices
    async createInvoice(data) {
        return this.request('/api/invoices', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async getInvoices(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/invoices?${query}`);
    }
    
    async getInvoice(id) {
        return this.request(`/api/invoices/${id}`);
    }
    
    async generateInvoicePDF(id) {
        window.open(`${this.baseUrl}/api/invoices/${id}/pdf`, '_blank');
    }
    
    // Clients
    async getClients(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/clients?${query}`);
    }
    
    async createClient(data) {
        return this.request('/api/clients', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async updateClient(id, data) {
        return this.request(`/api/clients?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    async deleteClient(id) {
        return this.request(`/api/clients?id=${id}`, {
            method: 'DELETE'
        });
    }
    
    // Quotations
    async getQuotations(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/quotations?${query}`);
    }
    
    async createQuotation(data) {
        return this.request('/api/quotations', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    async convertQuotationToInvoice(quotationId) {
        return this.request(`/api/quotations/${quotationId}/convert`, {
            method: 'POST'
        });
    }
    
    // Dashboard
    async getDashboardStats(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/dashboard?${query}`);
    }
}

// Create global instance
const mojoAPI = new MojoAPI();