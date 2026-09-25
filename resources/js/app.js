import "./bootstrap";

import Alpine from "alpinejs";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap";
import "@fortawesome/fontawesome-free/css/all.min.css";

window.Alpine = Alpine;

Alpine.start();

const toggleSidebar = document.getElementById("toggleSidebar");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("sidebarOverlay");

if (toggleSidebar) {
    toggleSidebar.addEventListener("click", () => {
        sidebar.classList.toggle("show");
        overlay.classList.toggle("active");
    });
}

if (overlay) {
    overlay.addEventListener("click", () => {
        sidebar.classList.remove("show");
        overlay.classList.remove("active");
    });
}

document.querySelectorAll(".toggle-password").forEach((button) => {
    button.addEventListener("click", function () {
        const input = document.getElementById(this.dataset.target);

        const icon = this.querySelector("i");

        if (input.type === "password") {
            input.type = "text";

            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";

            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const closeBtn = document.getElementById("closeSuccessModal");

    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            const modal = document.getElementById("successModal");

            modal.style.opacity = "0";

            setTimeout(() => {
                modal.remove();
            }, 250);
        });
    }
});
const imageInput = document.getElementById("image");

if (imageInput) {

    imageInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) return;

        // تغيير شكل البوكس
        document.getElementById("uploadBox")?.classList.add("changed");

        // تغيير الأيقونة
        const icon = document.getElementById("uploadIcon");
        if (icon) {
            icon.className = "fa-solid fa-circle-check upload-icon";
        }

        // تغيير النص
        const title = document.getElementById("uploadTitle");
        if (title) {
            title.textContent = "Image Changed";
        }

        const text = document.getElementById("uploadText");
        if (text) {
            text.textContent = file.name;
        }

        // Preview للصورة لو موجود
        const preview = document.getElementById("previewImage");

        if (preview) {

            const reader = new FileReader();

            reader.onload = function (e) {
                preview.src = e.target.result;
            };

            reader.readAsDataURL(file);

        }

    });

}