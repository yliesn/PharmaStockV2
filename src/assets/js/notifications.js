/**
 * Simple Notification System
 * Minimalist version of the complete system
 */
class NotificationSystem {
    /**
     * Initialize the notification system
     * @param {Object} options - Configuration options
     */
    constructor(options = {}) {
        // Default options
        this.options = {
            position: 'top-right',     // Notification position
            duration: 5000,            // Display duration in ms
            animationDuration: 300,    // Animation duration in ms
            ...options
        };
        
        // Initialize container
        this.container = null;
        this.notifications = [];
        
        // Create container and inject styles
        this.createContainer();
        this.injectStyles();
    }
    
    /**
     * Create the notifications container
     */
    createContainer() {
        this.container = document.createElement('div');
        this.container.className = `notification-container ${this.options.position}`;
        document.body.appendChild(this.container);
    }
    
    /**
     * Inject CSS styles
     */
    injectStyles() {
        const styleElement = document.createElement('style');
        styleElement.textContent = `
            .notification-container {
                position: fixed;
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 350px;
                width: 100%;
            }
            
            .notification-container.top-right {
                top: 20px;
                right: 20px;
            }
            
            .notification-container.top-left {
                top: 20px;
                left: 20px;
            }
            
            .notification-container.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            .notification-container.bottom-left {
                bottom: 20px;
                left: 20px;
            }
            
            .notification-item {
                background: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                transform: translateX(calc(100% + 40px));
                transition: transform ${this.options.animationDuration}ms ease;
                overflow: hidden;
            }
            
            .notification-container.top-left .notification-item,
            .notification-container.bottom-left .notification-item {
                transform: translateX(calc(-100% - 40px));
            }
            
            .notification-item.show {
                transform: translateX(0) !important;
            }
            
            .notification-item.success {
                border-left: 4px solid #4caf50;
            }
            
            .notification-item.error {
                border-left: 4px solid #f44336;
            }
            
            .notification-item.info {
                border-left: 4px solid #2196f3;
            }
            
            .notification-item.warning {
                border-left: 4px solid #ff9800;
            }
            
            .notification-icon {
                margin-right: 10px;
                font-size: 20px;
            }
            
            .notification-item.success .notification-icon {
                color: #4caf50;
            }
            
            .notification-item.error .notification-icon {
                color: #f44336;
            }
            
            .notification-item.info .notification-icon {
                color: #2196f3;
            }
            
            .notification-item.warning .notification-icon {
                color: #ff9800;
            }
            
            .notification-content {
                flex: 1;
            }
            
            .notification-title {
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 2px;
                color: #333;
            }
            
            .notification-message {
                font-size: 13px;
                color: #777;
            }
            
            .notification-close {
                color: #999;
                cursor: pointer;
                padding: 5px;
                margin-left: 10px;
            }
            
            .notification-item.hiding {
                opacity: 0;
                transform: translateX(calc(100% + 40px));
                transition: transform ${this.options.animationDuration}ms ease, opacity ${this.options.animationDuration}ms ease;
            }
            
            .notification-container.top-left .notification-item.hiding,
            .notification-container.bottom-left .notification-item.hiding {
                transform: translateX(calc(-100% - 40px)) !important;
            }
        `;
        document.head.appendChild(styleElement);
    }
    
    /**
     * Display a notification
     * @param {string} title - Notification title
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, info, warning)
     * @returns {Object} - Notification object with close() method
     */
    show(title, message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification-item ${type}`;
        
        // Determine icon based on type
        let iconContent = 'ℹ'; // info by default
        if (type === 'success') iconContent = '✓';
        else if (type === 'error') iconContent = '✕';
        else if (type === 'warning') iconContent = '⚠';
        
        // Internal structure of the notification
        notification.innerHTML = `
            <div class="notification-icon">${iconContent}</div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <div class="notification-close">✕</div>
        `;
        
        // Add notification to container
        this.container.appendChild(notification);
        
        // Force reflow to trigger animation
        notification.offsetHeight;
        
        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Handle closing
        const closeButton = notification.querySelector('.notification-close');
        
        // Close method
        const close = () => {
            notification.classList.add('hiding');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, this.options.animationDuration);
        };
        
        // Click event on close button
        closeButton.addEventListener('click', close);
        
        // Auto-close after specified duration
        if (this.options.duration > 0) {
            setTimeout(close, this.options.duration);
        }
        
        // Return notification object
        return { close };
    }
    
    /**
     * Display a success notification
     */
    success(title, message) {
        return this.show(title, message, 'success');
    }
    
    /**
     * Display an error notification
     */
    error(title, message) {
        return this.show(title, message, 'error');
    }
    
    /**
     * Display an info notification
     */
    info(title, message) {
        return this.show(title, message, 'info');
    }
    
    /**
     * Display a warning notification
     */
    warning(title, message) {
        return this.show(title, message, 'warning');
    }
}