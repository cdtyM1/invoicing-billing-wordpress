<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IBS_Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ibs_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_ibs_save_customer', [$this, 'ajax_save_customer']);
        add_action('wp_ajax_ibs_delete_customer', [$this, 'ajax_delete_customer']);
        add_action('wp_ajax_ibs_save_invoice', [$this, 'ajax_save_invoice']);
        add_action('wp_ajax_ibs_delete_invoice', [$this, 'ajax_delete_invoice']);
        add_action('wp_ajax_ibs_send_message', [$this, 'ajax_send_message']);
    }

    public function register_post_types() {
        register_post_type('ibs_customer', [
            'labels' => [
                'name' => __('Customers', 'tagihpro-billing'),
                'singular_name' => __('Customer', 'tagihpro-billing'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'has_archive' => false,
        ]);

        register_post_type('ibs_invoice', [
            'labels' => [
                'name' => __('Invoices', 'tagihpro-billing'),
                'singular_name' => __('Invoice', 'tagihpro-billing'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'has_archive' => false,
        ]);
    }

    public function admin_menu() {
        add_menu_page(
            'TagihPro',
            'TagihPro',
            'manage_options',
            'tagihpro-billing',
            [$this, 'render_dashboard_page'],
            'dashicons-money-alt',
            26
        );
    }

    public function enqueue_assets($hook) {
        if ('toplevel_page_tagihpro-billing' !== $hook) {
            return;
        }

        wp_enqueue_style('ibs-admin', plugin_dir_url(__DIR__) . 'assets/css/ibs-admin.css', [], '1.0.0');
        wp_enqueue_script('ibs-admin', plugin_dir_url(__DIR__) . 'assets/js/ibs-admin.js', ['jquery'], '1.0.0', true);

        $data = $this->get_dashboard_data();
        wp_localize_script('ibs-admin', 'ibsData', $data);
        wp_localize_script('ibs-admin', 'ibsSettings', [
            'nonce' => wp_create_nonce('ibs_nonce'),
        ]);
    }

    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $data = $this->get_dashboard_data();
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>TagihPro</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <script src="https://unpkg.com/lucide@latest"></script>
            <style>
                body { font-family: 'Inter', sans-serif; }
                ::-webkit-scrollbar { width: 6px; height: 6px; }
                ::-webkit-scrollbar-track { background: #f1f5f9; }
                ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
                ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
            </style>
        </head>
        <body class="h-full bg-slate-50 text-slate-800 antialiased flex flex-col">
            <div id="app" class="min-h-full flex flex-col">
                <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-600 text-white p-2 rounded-xl shadow-md shadow-indigo-100 flex items-center justify-center">
                                <i data-lucide="receipt" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-slate-900 tracking-tight">TagihPro<span class="text-indigo-600">.</span></h1>
                                <p class="text-xs text-slate-500 font-medium hidden sm:block">Sistem Penagihan, WhatsApp & Email Otomatis</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button onclick="openReminderSettingsModal()" class="hidden md:flex items-center space-x-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                                <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                                <span>Atur Pengingat Otomatis</span>
                            </button>
                            <button onclick="triggerAutoCheckReminders()" class="flex items-center space-x-2 px-3.5 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition shadow-sm">
                                <i data-lucide="bell-ring" class="w-4 h-4 text-indigo-600 animate-bounce"></i>
                                <span>Cek Jatuh Tempo Hari Ini</span>
                            </button>
                        </div>
                    </div>
                </header>

                <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                    <section aria-labelledby="stats-heading">
                        <h2 id="stats-heading" class="sr-only">Ringkasan Statistik Tagihan</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Tagihan</p>
                                    <h3 id="stat-total-amount" class="text-2xl font-bold text-slate-900 mt-1">Rp 0</h3>
                                    <p id="stat-total-count" class="text-xs text-slate-500 mt-1">0 invoice keseluruhan</p>
                                </div>
                                <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600">
                                    <i data-lucide="wallet" class="w-6 h-6"></i>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-500">Belum Lunas</p>
                                    <h3 id="stat-unpaid-amount" class="text-2xl font-bold text-slate-900 mt-1">Rp 0</h3>
                                    <p id="stat-unpaid-count" class="text-xs text-slate-500 mt-1">0 invoice pending</p>
                                </div>
                                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600">
                                    <i data-lucide="clock" class="w-6 h-6"></i>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-rose-500">Jatuh Tempo / Terlambat</p>
                                    <h3 id="stat-overdue-amount" class="text-2xl font-bold text-slate-900 mt-1">Rp 0</h3>
                                    <p id="stat-overdue-count" class="text-xs text-slate-500 mt-1">0 invoice perlu perhatian</p>
                                </div>
                                <div class="w-12 h-12 bg-rose-50 rounded-xl flex items-center justify-center text-rose-600">
                                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-500">Sudah Lunas</p>
                                    <h3 id="stat-paid-amount" class="text-2xl font-bold text-slate-900 mt-1">Rp 0</h3>
                                    <p id="stat-paid-count" class="text-xs text-slate-500 mt-1">0 invoice terselesaikan</p>
                                </div>
                                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 px-6 pt-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex space-x-2">
                                <button onclick="switchTab('invoices')" id="tab-btn-invoices" class="px-4 py-2.5 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600 transition">
                                    Daftar Invoice & Penagihan
                                </button>
                                <button onclick="switchTab('customers')" id="tab-btn-customers" class="px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition">
                                    Manajemen Customer
                                </button>
                                <button onclick="switchTab('logs')" id="tab-btn-logs" class="px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition flex items-center space-x-1.5">
                                    <span>Log Pesan WA & Email</span>
                                    <span id="log-count-badge" class="px-2 py-0.5 text-xs bg-slate-100 text-slate-600 rounded-full font-bold">0</span>
                                </button>
                            </div>

                            <div class="pb-3 flex items-center space-x-2">
                                <div id="invoice-actions" class="flex items-center space-x-2">
                                    <button onclick="openAddInvoiceModal()" class="flex items-center space-x-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                        <span>Buat Invoice Baru</span>
                                    </button>
                                </div>
                                <div id="customer-actions" class="hidden flex items-center space-x-2">
                                    <button onclick="openAddCustomerModal()" class="flex items-center space-x-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                                        <span>Tambah Customer</span>
                                    </button>
                                </div>
                                <div id="log-actions" class="hidden flex items-center space-x-2">
                                    <button onclick="clearLogs()" class="flex items-center space-x-2 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                                        <i data-lucide="trash-2" class="w-4 h-4 text-slate-500"></i>
                                        <span>Bersihkan Log</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div id="tab-content-invoices" class="space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="relative flex-1 max-w-sm">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                            <i data-lucide="search" class="w-4 h-4"></i>
                                        </span>
                                        <input type="text" id="search-invoice" oninput="renderInvoices()" placeholder="Cari nomor invoice atau customer..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 transition">
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <select id="filter-status" onchange="renderInvoices()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 focus:outline-none focus:border-indigo-500 transition">
                                            <option value="ALL">Semua Status</option>
                                            <option value="Belum Lunas">Belum Lunas</option>
                                            <option value="Jatuh Tempo">Jatuh Tempo</option>
                                            <option value="Lunas">Lunas</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                                <th class="py-3.5 px-4">Invoice / Customer</th>
                                                <th class="py-3.5 px-4">Nominal</th>
                                                <th class="py-3.5 px-4">Tgl Terbit & Jatuh Tempo</th>
                                                <th class="py-3.5 px-4">Status</th>
                                                <th class="py-3.5 px-4">Auto-Reminders</th>
                                                <th class="py-3.5 px-4 text-right">Aksi Cepat (WA & Email)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="invoice-table-body" class="divide-y divide-slate-100 text-xs text-slate-700"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="tab-content-customers" class="hidden space-y-4">
                                <div class="relative flex-1 max-w-sm">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="search" class="w-4 h-4"></i>
                                    </span>
                                    <input type="text" id="search-customer" oninput="renderCustomers()" placeholder="Cari nama, perusahaan, atau nomor WA..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 transition">
                                </div>

                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                                <th class="py-3.5 px-4">Nama Pelanggan & Perusahaan</th>
                                                <th class="py-3.5 px-4">Nomor WhatsApp</th>
                                                <th class="py-3.5 px-4">Email</th>
                                                <th class="py-3.5 px-4 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="customer-table-body" class="divide-y divide-slate-100 text-xs text-slate-700"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="tab-content-logs" class="hidden space-y-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-slate-500">Riwayat log pengiriman pesan pengingat tagihan melalui WhatsApp & Email Simulator.</p>
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                                <th class="py-3.5 px-4">Waktu</th>
                                                <th class="py-3.5 px-4">Channel</th>
                                                <th class="py-3.5 px-4">Penerima</th>
                                                <th class="py-3.5 px-4">Invoice Ref</th>
                                                <th class="py-3.5 px-4">Isi Pesan</th>
                                                <th class="py-3.5 px-4 text-right">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="log-table-body" class="divide-y divide-slate-100 text-xs text-slate-700"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <div id="modal-invoice" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <h3 id="invoice-modal-title" class="text-base font-bold text-slate-900">Buat Invoice Baru</h3>
                            <button onclick="closeModal('modal-invoice')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                        <form id="form-invoice" onsubmit="saveInvoice(event)" class="space-y-4">
                            <input type="hidden" id="invoice-edit-id">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Invoice</label>
                                <input type="text" id="inv-number" required placeholder="INV/2026/001" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Customer</label>
                                <select id="inv-customer-id" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500"></select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                                    <input type="number" id="inv-amount" required min="1000" placeholder="1500000" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Tagihan</label>
                                    <select id="inv-status" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                                        <option value="Belum Lunas">Belum Lunas</option>
                                        <option value="Jatuh Tempo">Jatuh Tempo</option>
                                        <option value="Lunas">Lunas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Terbit</label>
                                    <input type="date" id="inv-issue-date" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Jatuh Tempo (Due Date)</label>
                                    <input type="date" id="inv-due-date" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                                </div>
                            </div>
                            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                                <button type="button" onclick="closeModal('modal-invoice')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Batal</button>
                                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-100 transition">Simpan Invoice</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="modal-customer" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <h3 id="customer-modal-title" class="text-base font-bold text-slate-900">Tambah Customer Baru</h3>
                            <button onclick="closeModal('modal-customer')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                        <form id="form-customer" onsubmit="saveCustomer(event)" class="space-y-4">
                            <input type="hidden" id="cust-edit-id">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap Customer</label>
                                <input type="text" id="cust-name" required placeholder="Budi Santoso" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Perusahaan / Toko</label>
                                <input type="text" id="cust-company" required placeholder="PT Maju Sejahtera" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp (Format: 628xxxxxxxx)</label>
                                <input type="text" id="cust-phone" required placeholder="6281234567890" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email</label>
                                <input type="email" id="cust-email" required placeholder="budi@maju.co.id" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                                <button type="button" onclick="closeModal('modal-customer')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Batal</button>
                                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-100 transition">Simpan Customer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="modal-reminder-settings" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <h3 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                                <i data-lucide="settings" class="w-5 h-5 text-indigo-600"></i>
                                <span>Pengaturan Pengingat Otomatis (Cron Simulator)</span>
                            </h3>
                            <button onclick="closeModal('modal-reminder-settings')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                        <div class="space-y-4 text-xs text-slate-700">
                            <p class="text-slate-500">Sistem secara otomatis memeriksa tanggal jatuh tempo invoice dan mengirimkan pengingat terjadwal ke WhatsApp & Email pelanggan sesuai konfigurasi berikut:</p>

                            <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <label class="flex items-center justify-between cursor-pointer"><span class="font-semibold text-slate-800">H-3 (3 Hari Sebelum Jatuh Tempo)</span><input type="checkbox" id="rem-h3" checked class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"></label>
                                <label class="flex items-center justify-between cursor-pointer"><span class="font-semibold text-slate-800">H-1 (1 Hari Sebelum Jatuh Tempo)</span><input type="checkbox" id="rem-h1" checked class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"></label>
                                <label class="flex items-center justify-between cursor-pointer"><span class="font-semibold text-slate-800">Hari H (Tepat Hari Jatuh Tempo)</span><input type="checkbox" id="rem-h0" checked class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"></label>
                                <label class="flex items-center justify-between cursor-pointer"><span class="font-semibold text-slate-800">H+ (Terlambat / Lewat Jatuh Tempo)</span><input type="checkbox" id="rem-overdue" checked class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"></label>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Template Pesan WhatsApp</label>
                                <textarea id="tpl-wa" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 font-mono">Halo {nama}, Yth. dari {perusahaan}, kami mengingatkan bahwa invoice {invoice_no} sebesar {nominal} akan jatuh tempo pada {due_date}. Mohon segera lakukan pembayaran. Terima kasih!</textarea>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Template Subjek & Pesan Email</label>
                                <input type="text" id="tpl-email-subject" value="Pengingat Tagihan Pembayaran - {invoice_no}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 mb-2">
                                <textarea id="tpl-email-body" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 font-mono">Kepada Yth. {nama} ({perusahaan}),

Berikut adalah pengingat bahwa tagihan dengan nomor {invoice_no} senilai {nominal} jatuh tempo pada tanggal {due_date}.

Mohon abaikan jika pembayaran telah dilakukan.</textarea>
                            </div>
                        </div>
                        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                            <button type="button" onclick="closeModal('modal-reminder-settings')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Tutup</button>
                            <button type="button" onclick="saveReminderSettings()" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-100 transition">Simpan Pengaturan</button>
                        </div>
                    </div>
                </div>

                <div id="modal-simulator" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <h3 id="sim-title" class="text-base font-bold text-slate-900 flex items-center space-x-2">
                                <i data-lucide="send" class="w-5 h-5 text-indigo-600"></i>
                                <span>Simulasi Pengiriman Pesan</span>
                            </h3>
                            <button onclick="closeModal('modal-simulator')" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>

                        <div class="space-y-4">
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                                <div class="flex justify-between"><span class="text-slate-400">Channel:</span> <span id="sim-channel" class="font-bold text-indigo-600 uppercase">WhatsApp</span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Penerima:</span> <span id="sim-recipient" class="font-bold text-slate-800">-</span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Kontak:</span> <span id="sim-contact" class="font-semibold text-slate-700">-</span></div>
                                <div class="flex justify-between"><span class="text-slate-400">Invoice Ref:</span> <span id="sim-inv" class="font-bold text-slate-800">-</span></div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Preview Isi Pesan Otomatis</label>
                                <textarea id="sim-message" rows="5" class="w-full px-3.5 py-2.5 bg-slate-900 text-emerald-400 font-mono rounded-xl text-xs focus:outline-none" readonly></textarea>
                            </div>

                            <div id="sim-loading" class="hidden flex items-center justify-center space-x-2 py-3 text-indigo-600 text-xs font-semibold">
                                <i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>
                                <span>Mengirim pesan melalui Gateway API...</span>
                            </div>

                            <div id="sim-success" class="hidden p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-xs font-semibold flex items-center space-x-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                                <span>Pesan berhasil terkirim ke tujuan!</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100">
                            <button type="button" onclick="closeModal('modal-simulator')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">Tutup</button>
                            <button type="button" id="btn-execute-send" onclick="executeSendMessage()" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-100 transition flex items-center space-x-1.5">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                <span>Kirim Sekarang</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="toast" class="fixed bottom-5 right-5 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
                    <div class="bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-xl flex items-center space-x-3 text-xs font-medium">
                        <i id="toast-icon" data-lucide="check-circle-2" class="w-5 h-5 text-emerald-400"></i>
                        <span id="toast-msg">Notifikasi berhasil</span>
                    </div>
                </div>
            </div>

            <script>
                window.ibsDashboardData = <?php echo wp_json_encode($data); ?>;
            </script>
        </body>
        </html>
        <?php
    }

    public function get_dashboard_data() {
        $customers = get_posts([
            'post_type' => 'ibs_customer',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $invoices = get_posts([
            'post_type' => 'ibs_invoice',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $customerList = [];
        foreach ($customers as $customer) {
            $meta = get_post_meta($customer->ID);
            $customerList[] = [
                'id' => (string) $customer->ID,
                'name' => $meta['name'][0] ?? $customer->post_title,
                'company' => $meta['company'][0] ?? '',
                'phone' => $meta['phone'][0] ?? '',
                'email' => $meta['email'][0] ?? '',
            ];
        }

        $invoiceList = [];
        foreach ($invoices as $invoice) {
            $meta = get_post_meta($invoice->ID);
            $invoiceList[] = [
                'id' => (string) $invoice->ID,
                'number' => $meta['number'][0] ?? $invoice->post_title,
                'customerId' => $meta['customer_id'][0] ?? '',
                'amount' => (int) ($meta['amount'][0] ?? 0),
                'issueDate' => $meta['issue_date'][0] ?? date('Y-m-d'),
                'dueDate' => $meta['due_date'][0] ?? date('Y-m-d'),
                'status' => $meta['status'][0] ?? 'Belum Lunas',
                'remindersSent' => maybe_unserialize($meta['reminders_sent'][0] ?? 'a:0:{}') ?: [],
            ];
        }

        $logs = get_option('ibs_logs', [
            [
                'id' => 'sample_1',
                'time' => date('Y-m-d H:i'),
                'channel' => 'WhatsApp',
                'recipient' => 'Budi Santoso (PT Maju Sejahtera)',
                'invoice' => 'INV/2026/001',
                'message' => 'Halo Budi Santoso, tagihan INV/2026/001...',
                'status' => 'Terkirim',
            ],
        ]);

        return [
            'customers' => $customerList,
            'invoices' => $invoiceList,
            'logs' => $logs,
            'config' => [
                'h3' => true,
                'h1' => true,
                'h0' => true,
                'overdue' => true,
                'waTemplate' => "Halo {nama} dari {perusahaan},\n\nKami mengingatkan bahwa tagihan invoice #{invoice_no} senilai {nominal} akan jatuh tempo pada {due_date}.\n\nMohon lakukan pembayaran tepat waktu.\n\nTerima kasih!",
                'emailSubject' => 'Pengingat Tagihan Pembayaran - #{invoice_no}',
                'emailBody' => "Kepada Yth. {nama} ({perusahaan}),\n\nBerikut adalah pengingat bahwa tagihan dengan nomor #{invoice_no} senilai {nominal} akan jatuh tempo pada tanggal {due_date}.\n\nTerima kasih atas kerja samanya.",
            ],
        ];
    }

    public function ajax_get_dashboard_data() {
        wp_send_json_success($this->get_dashboard_data());
    }

    public function ajax_save_customer() {
        check_ajax_referer('ibs_nonce', 'nonce');

        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $name = sanitize_text_field(wp_unslash($_POST['name']));
        $company = sanitize_text_field(wp_unslash($_POST['company']));
        $phone = sanitize_text_field(wp_unslash($_POST['phone']));
        $email = sanitize_email(wp_unslash($_POST['email']));

        $post_id = $id ? (int) $id : wp_insert_post([
            'post_type' => 'ibs_customer',
            'post_status' => 'publish',
            'post_title' => $name,
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'name', $name);
            update_post_meta($post_id, 'company', $company);
            update_post_meta($post_id, 'phone', $phone);
            update_post_meta($post_id, 'email', $email);
            wp_update_post(['ID' => $post_id, 'post_title' => $name]);
        }

        wp_send_json_success(['message' => 'Customer saved']);
    }

    public function ajax_delete_customer() {
        check_ajax_referer('ibs_nonce', 'nonce');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id) {
            wp_delete_post($id, true);
        }
        wp_send_json_success(['message' => 'Customer deleted']);
    }

    public function ajax_save_invoice() {
        check_ajax_referer('ibs_nonce', 'nonce');

        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $number = sanitize_text_field(wp_unslash($_POST['number']));
        $customer_id = sanitize_text_field(wp_unslash($_POST['customerId']));
        $amount = floatval($_POST['amount']);
        $status = sanitize_text_field(wp_unslash($_POST['status']));
        $issue_date = sanitize_text_field(wp_unslash($_POST['issueDate']));
        $due_date = sanitize_text_field(wp_unslash($_POST['dueDate']));

        $post_id = $id ? (int) $id : wp_insert_post([
            'post_type' => 'ibs_invoice',
            'post_status' => 'publish',
            'post_title' => $number,
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'number', $number);
            update_post_meta($post_id, 'customer_id', $customer_id);
            update_post_meta($post_id, 'amount', $amount);
            update_post_meta($post_id, 'status', $status);
            update_post_meta($post_id, 'issue_date', $issue_date);
            update_post_meta($post_id, 'due_date', $due_date);
            update_post_meta($post_id, 'reminders_sent', []);
            wp_update_post(['ID' => $post_id, 'post_title' => $number]);
        }

        wp_send_json_success(['message' => 'Invoice saved']);
    }

    public function ajax_delete_invoice() {
        check_ajax_referer('ibs_nonce', 'nonce');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id) {
            wp_delete_post($id, true);
        }
        wp_send_json_success(['message' => 'Invoice deleted']);
    }

    public function ajax_send_message() {
        check_ajax_referer('ibs_nonce', 'nonce');

        $payload = isset($_POST['payload']) ? $_POST['payload'] : [];
        $logs = get_option('ibs_logs', []);
        $logs = is_array($logs) ? $logs : [];

        $logs[] = [
            'id' => 'log_' . time(),
            'time' => current_time('Y-m-d H:i'),
            'channel' => $payload['channel'] ?? 'WhatsApp',
            'recipient' => $payload['recipient'] ?? '-',
            'invoice' => $payload['invoice'] ?? '-',
            'message' => $payload['message'] ?? '',
            'status' => 'Terkirim',
        ];

        update_option('ibs_logs', $logs);
        wp_send_json_success(['message' => 'Message sent']);
    }
}
