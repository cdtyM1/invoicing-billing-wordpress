# TagihPro Billing System

Plugin WordPress untuk dashboard penagihan & invoice pelanggan seperti mockup HTML yang Anda berikan.

## Fitur

- Dashboard ringkasan tagihan
- Manajemen customer
- Manajemen invoice
- Filter invoice berdasarkan status
- Simulasi pengiriman WhatsApp & Email
- Log pengiriman
- Pengaturan reminder otomatis
- Admin page di WordPress

## Cara Instalasi

1. Upload folder plugin ke `wp-content/plugins/`
2. Aktifkan plugin `TagihPro Billing System`
3. Buka menu sidebar **TagihPro** di WordPress Admin
4. Gunakan dashboard yang tersedia untuk input customer dan invoice

## Struktur

- `invoicing-billing-system.php` — file utama plugin
- `includes/class-ibs-plugin.php` — logika plugin dan admin dashboard
- `assets/css/ibs-admin.css` — styling custom dashboard
- `assets/js/ibs-admin.js` — logika dashboard

## Catatan

Plugin ini dibuat sebagai versi WordPress yang mengadaptasi mockup HTML Anda. Anda bisa terus kembangkan untuk:

- koneksi ke WhatsApp API
- koneksi ke email SMTP / SendGrid / Mailgun
- database lebih lanjut untuk multi-user
- export PDF invoice

