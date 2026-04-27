# Al-Khair Pharmacy & Store – Frontend

This repository contains the **frontend application** for **Al-Khair Pharmacy & Store**, built with modern web technologies to deliver a fast, responsive, and user-friendly premium shopping at Bauchi's most trusted retail destination, offering quality healthcare, electronics, and everyday essentials, all under one roof.

The frontend is designed to work as a **static React application** and can connect to a backend API (e.g. Laravel) for dynamic features such as product management, orders, and admin control.

---

## 🌐 Live Demo

**Demo URL (GitHub Pages):**
[https://brilliant-smart.github.io/al-khair-pharmacy-store/](https://brilliant-smart.github.io/al-khair-pharmacy-store/)

> Note: The GitHub Pages version is for demonstration only.
> Production deployment is intended for shared or independent hosting.

---

## 📁 Project Overview

**Frontend Stack**

* Vite
* React
* TypeScript
* Tailwind CSS
* shadcn/ui

**Architecture**

* Frontend: Static SPA (this repository)
* Backend: API-based (recommended: Laravel)
* Deployment: FTP / cPanel / shared hosting

---

## 🛠️ Getting Started (Local Development)

### Prerequisites

Make sure you have the following installed:

* **Node.js (v18 or later recommended)**
* **npm** or **yarn**

---

### Installation & Setup

```sh
# 1. Clone the repository
git clone <YOUR_GIT_URL>

# 2. Navigate into the project folder
cd alkhair-pharmacy-store

# 3. Install dependencies
npm install

# 4. Start the development server
npm run dev
```

The app will be available at:

```
http://localhost:5173
```

---

## 🏗️ Building for Production

To generate production-ready static files:

```sh
npm run build
```

This will create a `dist/` folder containing optimized assets.

---

## 🚀 Deployment (Traditional Hosting / FTP)

This project is optimized for **traditional hosting environments**.

### Deployment Steps

1. Run `npm run build`
2. Open your FTP client (e.g. FileZilla)
3. Upload **all contents inside `dist/`** to:

   ```
   public_html/
   ```
4. (Optional but recommended) Add an `.htaccess` file for SPA routing

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

---

## 🔌 Backend Integration

This frontend is designed to consume a backend API.

### Recommended Backend

* **Laravel (API-only)**
* MySQL database
* Authentication (Laravel Sanctum)
* Admin CRUD for products

Example API usage:

```ts
fetch("https://api.yourdomain.com/api/products")
  .then(res => res.json())
  .then(data => setProducts(data));
```

---

## 🔐 Admin Features (Planned / Backend-Driven)

* Product CRUD (Create, Read, Update, Delete)
* Product categories
* Image uploads
* Inventory & pricing management
* Secure admin authentication

---

## 📦 Environment Variables

Create a `.env` file for local development if needed:

```env
VITE_API_URL=https://api.yourdomain.com/api
```

Use it in the app:

```ts
const API_URL = import.meta.env.VITE_API_URL;
```

---

## 📌 Notes

* This repository contains **frontend code only**
* No Node.js runtime is required on the server
* Backend and database are deployed separately
* Ideal for shared hosting, VPS, or cloud hosting

---

## 📄 License

This project is proprietary and developed for **Al-Khair Pharmacy & Store, Bauchi**.
