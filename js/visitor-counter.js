// visitor-counter.js - Enhanced Version
class VisitorCounter {
  constructor(options = {}) {
    // Configuration with defaults
    this.config = {
      counterElementId: 'visitor-count',
      activeVisitorsId: 'active-visitors',
      storageKey: 'fayda_saccos_visitor_',
      apiEndpoint: null,
      showUniqueVisitors: true,
      showLiveCounter: true,
      animationDuration: 2000,
      debug: false,
      ...options
    };

    // Elements
    this.counterElement = document.getElementById(this.config.counterElementId);
    this.activeVisitorsElement = document.getElementById(this.config.activeVisitorsId);
    
    if (!this.counterElement) {
      if (this.config.debug) console.warn('Visitor counter element not found');
      return;
    }

    // State
    this.activeVisitorsInterval = null;
    this.animationFrame = null;

    // Initialize
    this.init();
  }

  async init() {
    try {
      // Show loading state
      this.counterElement.textContent = 'Loading...';
      if (this.activeVisitorsElement) {
        this.activeVisitorsElement.textContent = '0';
      }

      if (this.config.apiEndpoint) {
        await this.updateServerCount();
      } else {
        this.updateLocalCount();
      }

      if (this.config.showLiveCounter) {
        this.animateCounter();
      }

      if (this.activeVisitorsElement) {
        this.startActiveVisitorsSimulation();
      }
    } catch (error) {
      this.handleError(error);
    }
  }

  updateLocalCount() {
    try {
      // Get current date
      const today = new Date().toDateString();
      const dateKey = this.config.storageKey + 'date';

      // Get or initialize counts
      let totalCount = localStorage.getItem(this.config.storageKey + 'total') || 1000;
      let uniqueCount = localStorage.getItem(this.config.storageKey + 'unique') || 800;
      let lastDate = localStorage.getItem(dateKey);

      // Convert to numbers
      totalCount = parseInt(totalCount);
      uniqueCount = parseInt(uniqueCount);

      // Check if this is a new day
      if (lastDate !== today) {
        localStorage.setItem(dateKey, today);
        
        // Daily count (optional)
        const dailyKey = this.config.storageKey + 'daily_' + today;
        let dailyCount = localStorage.getItem(dailyKey) || 0;
        dailyCount = parseInt(dailyCount) + 1;
        localStorage.setItem(dailyKey, dailyCount);
      }

      // Check if this is a new session
      if (!sessionStorage.getItem(this.config.storageKey + 'session')) {
        uniqueCount++;
        sessionStorage.setItem(this.config.storageKey + 'session', 'true');
      }

      // Always increment total count
      totalCount++;

      // Save updated counts
      localStorage.setItem(this.config.storageKey + 'total', totalCount);
      localStorage.setItem(this.config.storageKey + 'unique', uniqueCount);

      // Display the count
      const displayCount = this.config.showUniqueVisitors ? uniqueCount : totalCount;
      this.updateDisplay(displayCount, totalCount, uniqueCount);

      if (this.config.debug) {
        console.log('Visitor counts updated:', {
          total: totalCount,
          unique: uniqueCount,
          element: this.counterElement
        });
      }
    } catch (error) {
      this.handleError(error);
    }
  }

  updateDisplay(count, total, unique) {
    this.counterElement.textContent = this.formatNumber(count);
    this.counterElement.setAttribute('title', `${this.formatNumber(total)} total views`);
    this.counterElement.dataset.total = total;
    this.counterElement.dataset.unique = unique;
  }

  async updateServerCount() {
    try {
      const response = await fetch(this.config.apiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          referrer: document.referrer,
          path: window.location.pathname,
          isNewSession: !sessionStorage.getItem(this.config.storageKey + 'session')
        })
      });

      if (!response.ok) throw new Error('Network response was not ok');

      const data = await response.json();
      
      // Mark session as recorded
      if (data.isNewSession) {
        sessionStorage.setItem(this.config.storageKey + 'session', 'true');
      }
      
      this.updateDisplay(
        this.config.showUniqueVisitors ? (data.uniqueCount || data.count) : (data.totalCount || data.count),
        data.totalCount || data.count,
        data.uniqueCount || data.count
      );

      if (this.config.debug) {
        console.log('Server count received:', data);
      }
    } catch (error) {
      // Fallback to local counting if server fails
      if (this.config.debug) console.error('Server count failed, falling back to local:', error);
      this.updateLocalCount();
    }
  }

  animateCounter() {
    // Cancel any existing animation
    if (this.animationFrame) {
      cancelAnimationFrame(this.animationFrame);
    }

    const currentValue = parseInt(this.counterElement.textContent.replace(/,/g, '')) || 0;
    const target = parseInt(this.counterElement.dataset.total || this.counterElement.dataset.unique || currentValue);
    const start = Math.max(0, target - Math.min(100, target * 0.1));
    const duration = this.config.animationDuration;
    const startTime = performance.now();

    const animate = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const value = Math.floor(progress * (target - start) + start);
      
      this.counterElement.textContent = this.formatNumber(value);
      
      if (progress < 1) {
        this.animationFrame = requestAnimationFrame(animate);
      } else {
        this.counterElement.textContent = this.formatNumber(target);
      }
    };

    this.counterElement.textContent = this.formatNumber(start);
    this.animationFrame = requestAnimationFrame(animate);
  }

  startActiveVisitorsSimulation() {
    // Clear any existing interval
    if (this.activeVisitorsInterval) {
      clearInterval(this.activeVisitorsInterval);
    }

    // Initial value
    let activeCount = Math.floor(Math.random() * 50) + 1;
    this.activeVisitorsElement.textContent = activeCount;

    // Update periodically with some variation
    this.activeVisitorsInterval = setInterval(() => {
      // Random walk - slightly increase or decrease
      activeCount += Math.floor(Math.random() * 5) - 2;
      // Keep within reasonable bounds
      activeCount = Math.max(1, Math.min(100, activeCount));
      this.activeVisitorsElement.textContent = activeCount;
    }, 30000); // Update every 30 seconds
  }

  formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  handleError(error) {
    console.error('Visitor counter error:', error);
    this.counterElement.textContent = this.formatNumber(1000); // Fallback value
    this.counterElement.style.color = '#ccc';
    
    if (this.activeVisitorsElement) {
      this.activeVisitorsElement.textContent = '10';
    }
  }

  destroy() {
    // Clean up
    if (this.animationFrame) {
      cancelAnimationFrame(this.animationFrame);
    }
    if (this.activeVisitorsInterval) {
      clearInterval(this.activeVisitorsInterval);
    }
  }

  // Static method for quick initialization
  static autoInit(options = {}) {
    document.addEventListener('DOMContentLoaded', () => {
      new VisitorCounter(options);
    });
  }
}

// Initialize automatically if script is loaded directly
if (typeof module === 'undefined' && typeof window !== 'undefined') {
  // Browser environment - auto initialize
  VisitorCounter.autoInit();
} else if (typeof module !== 'undefined') {
  // Module environment - export class
  module.exports = VisitorCounter;
}