// Initialize EmailJS (v4+)
emailjs.init({
  publicKey: "RFl2-4eHenzarWon4",
});

window.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactForm');
  const formMessage = document.getElementById('formMessage');
  const spinner = document.getElementById('loadingSpinner');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const message = document.getElementById('message').value.trim();

    formMessage.textContent = '';
    formMessage.style.display = 'block';

    if (!name || !email || !message) {
      formMessage.textContent = '⚠️ Please fill in all fields.';
      formMessage.style.color = 'red';
      return;
    }

    if (name.length < 5) {
      formMessage.textContent = '⚠️ Full Name must be at least 5 characters.';
      formMessage.style.color = 'red';
      return;
    }

    if (!/^[A-Za-z\s]+$/.test(name)) {
      formMessage.textContent = '⚠️ Name must only contain letters and spaces.';
      formMessage.style.color = 'red';
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      formMessage.textContent = '⚠️ Invalid email address.';
      formMessage.style.color = 'red';
      return;
    }

    if (message.length < 5) {
      formMessage.textContent = '⚠️ Message must be at least 5 characters.';
      formMessage.style.color = 'red';
      return;
    }

    if (message.length > 300) {
      formMessage.textContent = '⚠️ Message cannot exceed 300 characters.';
      formMessage.style.color = 'red';
      return;
    }

    // ✅ Show spinner
    spinner.style.display = 'block';

    // Send email
    emailjs.send("service_e594fkz", "template_wzft06q", {
      from_name: name,
      from_email: email,
      message: message
    })
    .then(() => {
      spinner.style.display = 'none'; // ✅ Hide spinner
      formMessage.textContent = '✅ Message sent successfully!';
      formMessage.style.color = 'green';
      form.reset();
      setTimeout(() => {
        formMessage.style.display = 'none';
      }, 3000);
    })
    .catch((error) => {
      spinner.style.display = 'none'; // ✅ Hide spinner
      formMessage.textContent = '❌ Failed to send message. Try again.';
      formMessage.style.color = 'red';
      console.error('EmailJS Error:', error);
    });
  });
});
