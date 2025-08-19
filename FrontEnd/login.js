// Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyBorxcVPBtSOMp7zUZ2_4NQHmwRKPFvlog",
    authDomain: "mojoenterprise-dfb82.firebaseapp.com",
    projectId: "mojoenterprise-dfb82",
    storageBucket: "mojoenterprise-dfb82.firebasestorage.app",
    messagingSenderId: "847292925516",
    appId: "1:847292925516:web:94be20251639fe659796e9"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const auth = firebase.auth();

// DOM elements
const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const errorDiv = document.getElementById('loginError');

// Login function
loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const email = emailInput.value;
    const password = passwordInput.value;
    
    // Show loading state
    const submitBtn = loginForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...';

    // Firebase authentication
    auth.signInWithEmailAndPassword(email, password)
        .then((userCredential) => {
            // Successful login
            const user = userCredential.user;
            
            // Store the auth token in localStorage
            user.getIdToken().then((token) => {
                localStorage.setItem('authToken', token);
                localStorage.setItem('userEmail', user.email);
                
                // Redirect to invoice page
                const redirectUrl = localStorage.getItem('redirectUrl') || 'invoice.html';
                localStorage.removeItem('redirectUrl');
                window.location.href = redirectUrl;
            });
        })
        .catch((error) => {
            // Handle errors
            errorDiv.textContent = error.message;
            errorDiv.classList.remove('d-none');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
        });
});

// Check if user is already logged in
auth.onAuthStateChanged((user) => {
    if (user) {
        // User is signed in, redirect to invoice page
        window.location.href = 'invoice.html';
    }
});