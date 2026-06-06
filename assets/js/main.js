// Global CSRF Protection for Fetch API
(function () {
  const originalFetch = window.fetch;
  window.fetch = function () {
    let [resource, config] = arguments;
    if (
      config &&
      ["POST", "PUT", "DELETE", "PATCH"].includes(config.method?.toUpperCase())
    ) {
      const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
      if (token) {
        config.headers = {
          ...config.headers,
          "X-CSRF-TOKEN": token,
        };
      }
    }
    return originalFetch(resource, config);
  };
})();

// Mobile Navigation Toggle
const hamburger = document.getElementById("hamburger");
const navMenu = document.getElementById("nav-menu");

if (hamburger) {
  hamburger.addEventListener("click", () => {
    navMenu.classList.toggle("active");

    // Animate hamburger
    const spans = hamburger.querySelectorAll("span");
    if (navMenu.classList.contains("active")) {
      spans[0].style.transform = "rotate(-45deg) translate(-5px, 6px)";
      spans[1].style.opacity = "0";
      spans[2].style.transform = "rotate(45deg) translate(-5px, -6px)";
    } else {
      spans[0].style.transform = "none";
      spans[1].style.opacity = "1";
      spans[2].style.transform = "none";
    }
  });
}

// Close mobile menu when clicking on a link
const navLinks = document.querySelectorAll(".nav-menu a");
navLinks.forEach((link) => {
  link.addEventListener("click", (e) => {
    // Không đóng menu nếu bấm vào thẻ hiển thị dropdown
    if (link.getAttribute("href") === "#") return;

    navMenu.classList.remove("active");
    if (hamburger) {
      const spans = hamburger.querySelectorAll("span");
      spans[0].style.transform = "none";
      spans[1].style.opacity = "1";
      spans[2].style.transform = "none";
    }
  });
});

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    const href = this.getAttribute("href");
    if (href === "#") return; // Bỏ qua link "#" dùng cho JS toggle

    e.preventDefault();
    try {
      const target = document.querySelector(href);
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    } catch (err) {}
  });
});

// Scroll to Top Button
const scrollToTopBtn = document.createElement("button");
scrollToTopBtn.innerHTML =
  '<span class="material-symbols-rounded">arrow_upward</span>';

scrollToTopBtn.className = "scroll-to-top";
document.body.appendChild(scrollToTopBtn);

window.addEventListener("scroll", () => {
  if (window.pageYOffset > 300) {
    scrollToTopBtn.classList.add("visible");
  } else {
    scrollToTopBtn.classList.remove("visible");
  }
});

scrollToTopBtn.addEventListener("click", () => {
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });
});

// Add animation on scroll
const observerOptions = {
  threshold: 0.1,
  rootMargin: "0px 0px -50px 0px",
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = "1";
      entry.target.style.transform = "translateY(0)";
    }
  });
}, observerOptions);

// Observe elements
document.addEventListener("DOMContentLoaded", () => {
  const animateElements = document.querySelectorAll(
    ".feature-card, .room-card, .vm-card, .team-card, .why-item",
  );

  animateElements.forEach((el) => {
    el.style.opacity = "0";
    el.style.transform = "translateY(30px)";
    el.style.transition = "all 0.6s ease-out";
    observer.observe(el);
  });
});

// Loading animation
window.addEventListener("load", () => {
  document.body.classList.add("loaded");
});

// Format number with thousand separator
function formatCurrency(number) {
  return new Intl.NumberFormat("vi-VN").format(number);
}

// Form validation helper
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePhone(phone) {
  const re = /^[0-9]{10,11}$/;
  return re.test(phone);
}

// Show notification
function showNotification(message, type = "success") {
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <i class="fas fa-${type === "success" ? "check-circle" : "exclamation-circle"}"></i>
        <span>${message}</span>
    `;
  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === "success" ? "#27ae60" : "#e74c3c"};
        color: white;
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideOutRight 0.3s ease-out";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add CSS animations
const style = document.createElement("style");
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    .scroll-to-top:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(30, 144, 255, 0.3);
    }
`;
document.head.appendChild(style);

// 3D perspective tilt effect on room-card hover
document.addEventListener("DOMContentLoaded", () => {
    const cards = document.querySelectorAll(".room-card");
    cards.forEach(card => {
        card.addEventListener("mousemove", (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const xc = rect.width / 2;
            const yc = rect.height / 2;
            
            // Calculate tilt degrees (-15 to 15)
            const angleX = ((yc - y) / yc) * 15;
            const angleY = ((x - xc) / xc) * 15;
            
            // Apply transform instantly on hover move
            card.style.transition = "transform 0.1s ease-out, box-shadow 0.3s ease";
            card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-12px) scale(1.04)`;
        });
        
        card.addEventListener("mouseleave", () => {
            // Restore smooth transition to return to base state
            card.style.transition = "transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.6s ease";
            card.style.transform = "perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px) scale(1)";
        });
    });
});

console.log("Mái Nhà Xanh - Website initialized successfully!");
