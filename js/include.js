// Function to include HTML files
async function includeHTML() {
    const includes = document.querySelectorAll('[data-include]');
    
    for (const include of includes) {
        const file = include.getAttribute('data-include');
        try {
            const response = await fetch(file);
            if (response.ok) {
                include.innerHTML = await response.text();
            } else {
                throw new Error(`${file} not found`);
            }
        } catch (error) {
            console.error('Error including file:', error);
        }
    }
}

// Run when DOM is loaded
document.addEventListener('DOMContentLoaded', includeHTML);
