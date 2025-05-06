// Main initialization script
document.addEventListener('DOMContentLoaded', function() {
    // Load header and footer
    loadComponents();
    
    // Initialize page components
    initializePage();
    
    // Initialize footer functionalities
    initializeFooter();
});

function loadComponents() {
    // Load header
    fetch('includes/header.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('header-placeholder').innerHTML = data;
        });

    // Load footer
    fetch('includes/footer.html')
        .then(response => response.text())
        .then(data => {
            document.getElementById('footer-placeholder').innerHTML = data;
            initializeFooter();
        });
}

function initializePage() {
    // Floating header effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });
    }

    // Update datetime
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const datetimeElement = document.getElementById('datetime');
        if (datetimeElement) {
            datetimeElement.textContent = now.toLocaleDateString('en-US', options);
        }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
}

function initializeFooter() {
    // Set current year
    document.getElementById('current-year').textContent = new Date().getFullYear();
    
    // Back to top button
    const backToTopButton = document.querySelector('.btn-back-to-top');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Newsletter form
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('newsletterEmail').value;
            
            // Here you would typically send the email to your server
            console.log('Subscribed email:', email);
            
            // Show success message
            alert('Thank you for subscribing to our newsletter!');
            this.reset();
        });
    }

    // Visitor counter
    function updateVisitorCount() {
        // In a real implementation, you would fetch this from your analytics
        const totalVisitors = Math.floor(1000 + Math.random() * 5000);
        const activeVisitors = Math.floor(1 + Math.random() * 50);
        
        const visitorCount = document.getElementById('visitor-count');
        const activeVisitorsElement = document.getElementById('active-visitors');
        
        if (visitorCount) visitorCount.textContent = totalVisitors.toLocaleString();
        if (activeVisitorsElement) activeVisitorsElement.textContent = activeVisitors;
    }
    
    updateVisitorCount();
    setInterval(updateVisitorCount, 30000);

    // Cookie Consent
    const cookieConsent = document.getElementById('cookieConsent');
    const acceptCookies = document.getElementById('acceptCookies');
    const customizeCookies = document.getElementById('customizeCookies');
    const saveCookieSettings = document.getElementById('saveCookieSettings');
    const acceptCookiesFromModal = document.getElementById('acceptCookiesFromModal');

    // Check if cookie consent was already given
    if (cookieConsent && !localStorage.getItem('cookieConsent')) {
        setTimeout(() => {
            cookieConsent.style.display = 'block';
        }, 2000);
    }

    // Cookie functions
    function acceptAllCookies() {
        localStorage.setItem('cookieConsent', 'all');
        if (cookieConsent) cookieConsent.style.display = 'none';
        console.log('All cookies accepted');
    }

    function saveCustomPreferences() {
        const analytics = document.getElementById('analyticsCookies').checked;
        const marketing = document.getElementById('marketingCookies').checked;

        localStorage.setItem('cookieConsent', 'custom');
        localStorage.setItem('analyticsCookies', analytics);
        localStorage.setItem('marketingCookies', marketing);

        console.log('Cookie preferences saved:', { analytics, marketing });

        if (cookieConsent) cookieConsent.style.display = 'none';
        bootstrap.Modal.getInstance(document.getElementById('cookieSettingsModal')).hide();
    }

    // Event listeners
    if (acceptCookies) acceptCookies.addEventListener('click', acceptAllCookies);
    if (acceptCookiesFromModal) acceptCookiesFromModal.addEventListener('click', acceptAllCookies);
    if (customizeCookies) customizeCookies.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cookieSettingsModal')).show();
    });
    if (saveCookieSettings) saveCookieSettings.addEventListener('click', saveCustomPreferences);
}
