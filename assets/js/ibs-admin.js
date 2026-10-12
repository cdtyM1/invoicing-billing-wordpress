(function ($) {
    const initialData = window.ibsDashboardData || { customers: [], invoices: [], logs: [], config: {} };

    let customers = initialData.customers || [];
    let invoices = initialData.invoices || [];
    let logs = initialData.logs || [];
    let reminderConfig = initialData.config || {
        h3: true,
        h1: true,
        h0: true,
        overdue: true,
        waTemplate: "Halo {nama} dari {perusahaan},\n\nKami mengingatkan bahwa tagihan invoice #{invoice_no} senilai {nominal} akan jatuh tempo pada {due_date}.\n\nMohon lakukan pembayaran tepat waktu.\n\nTerima kasih!",
        emailSubject: "Pengingat Tagihan Pembayaran - #{invoice_no}",
        emailBody: "Kepada Yth. {nama} ({perusahaan}),\n\nBerikut adalah pengingat bahwa tagihan dengan nomor #{invoice_no} senilai {nominal} akan jatuh tempo pada tanggal {due_date}.\n\nTerima kasih atas kerja samanya."
    };

    let currentSimulation = null;

    $(document).ready(function () {
        lucide.createIcons();
        updateDashboard();
        renderInvoices();
        renderCustomers();
        renderLogs();
        populateCustomerDropdown();
    });

    function formatIDR(amount) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount || 0);
    }

    window.switchTab = function (tabName) {
        ['invoices', 'customers', 'logs'].forEach((t) => {
            $('#tab-content-' + t).addClass('hidden');
            $('#tab-btn-' + t).removeClass('border-indigo-600 text-indigo-600').addClass('border-transparent text-slate-500');
            $('#' + t + '-actions').addClass('hidden');
        });

        $('#tab-content-' + tabName).removeClass('hidden');
        $('#tab-btn-' + tabName).addClass('border-indigo-600 text-indigo-600').removeClass('border-transparent text-slate-500');
        $('#' + tabName + '-actions').removeClass('hidden');
        lucide.createIcons();
    }

    function updateDashboard() {
        let totalAmt = 0, unpaidAmt = 0, overdueAmt = 0, paidAmt = 0;
        let totalCnt = invoices.length, unpaidCnt = 0, overdueCnt = 0, paidCnt = 0;

        invoices.forEach(function (inv) {
            totalAmt += Number(inv.amount || 0);
            if (inv.status === 'Belum Lunas') {
                unpaidAmt += Number(inv.amount || 0);
                unpaidCnt++;
            } else if (inv.status === 'Jatuh Tempo') {
                overdueAmt += Number(inv.amount || 0);
                overdueCnt++;
            } else if (inv.status === 'Lunas') {
                paidAmt += Number(inv.amount || 0);
                paidCnt++;
            }
        });

        $('#stat-total-amount').text(formatIDR(totalAmt));
        $('#stat-total-count').text(totalCnt + ' invoice keseluruhan');
        $('#stat-unpaid-amount').text(formatIDR(unpaidAmt));
        $('#stat-unpaid-count').text(unpaidCnt + ' invoice pending');
        $('#stat-overdue-amount').text(formatIDR(overdueAmt));
        $('#stat-overdue-count').text(overdueCnt + ' invoice perlu perhatian');
        $('#stat-paid-amount').text(formatIDR(paidAmt));
        $('#stat-paid-count').text(paidCnt + ' invoice terselesaikan');
        $('#log-count-badge').text(logs.length);
    }

    function renderInvoices() {
        const tbody = $('#invoice-table-body');
        const search = $('#search-invoice').val().toLowerCase();
        const filterStatus = $('#filter-status').val();

        tbody.empty();

        const filtered = invoices.filter(function (inv) {
            const cust = customers.find(c => c.id === String(inv.customerId)) || {};
            const matchSearch = (inv.number || '').toLowerCase().includes(search) || (cust.name || '').toLowerCase().includes(search) || (cust.company || '').toLowerCase().includes(search);
            const matchStatus = filterStatus === 'ALL' || inv.status === filterStatus;
            return matchSearch && matchStatus;
        });

        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-8 text-slate-400">Tidak ada data invoice ditemukan.</td></tr>');
            return;
        }

        filtered.forEach(function (inv) {
            const cust = customers.find(c => c.id === String(inv.customerId)) || { name: 'Unknown', company: '-', phone: '', email: '' };

            let badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
            if (inv.status === 'Lunas') badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            if (inv.status === 'Jatuh Tempo') badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';

            const reminders = (inv.remindersSent || []).map(r => '<span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-semibold text-[10px] rounded-md border border-indigo-100">' + r + '</span>').join(' ');
            const remindersHtml = reminders || '<span class="text-slate-400 text-[11px] italic">Belum ada</span>';

            const tr = $('<tr class="hover:bg-slate-50/80 transition"></tr>');
            tr.html(`
                <td class="py-3 px-4">
                    <div class="font-bold text-slate-900">${inv.number}</div>
                    <div class="text-[11px] text-slate-500">${cust.name} • <span class="font-medium text-slate-600">${cust.company}</span></div>
                </td>
                <td class="py-3 px-4 font-semibold text-slate-900">${formatIDR(inv.amount)}</td>
                <td class="py-3 px-4">
                    <div class="text-slate-700">Terbit: ${inv.issueDate}</div>
                    <div class="text-[11px] font-medium text-rose-600">Jatuh Tempo: ${inv.dueDate}</div>
                </td>
                <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold border ${badgeClass}">${inv.status}</span></td>
                <td class="py-3 px-4"><div class="flex flex-wrap gap-1">${remindersHtml}</div></td>
                <td class="py-3 px-4 text-right">
                    <div class="flex items-center justify-end space-x-1.5">
                        <button onclick="openSimulator('WhatsApp', '${inv.id}')" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg transition border border-emerald-200 flex items-center space-x-1 px-2.5 text-[11px] font-semibold"><i data-lucide="message-square" class="w-3.5 h-3.5"></i><span>WA</span></button>
                        <button onclick="openSimulator('Email', '${inv.id}')" class="p-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg transition border border-sky-200 flex items-center space-x-1 px-2.5 text-[11px] font-semibold"><i data-lucide="mail" class="w-3.5 h-3.5"></i><span>Email</span></button>
                        <button onclick="editInvoice('${inv.id}')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                        <button onclick="deleteInvoice('${inv.id}')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                    </div>
                </td>
            `);
            tbody.append(tr);
        });
        lucide.createIcons();
    }

    function renderCustomers() {
        const tbody = $('#customer-table-body');
        const search = $('#search-customer').val().toLowerCase();
        tbody.empty();

        const filtered = customers.filter(function (c) {
            return (c.name || '').toLowerCase().includes(search) || (c.company || '').toLowerCase().includes(search) || (c.phone || '').includes(search);
        });

        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center py-8 text-slate-400">Tidak ada data customer ditemukan.</td></tr>');
            return;
        }

        filtered.forEach(function (c) {
            const tr = $('<tr class="hover:bg-slate-50/80 transition"></tr>');
            tr.html(`
                <td class="py-3 px-4"><div class="font-bold text-slate-900">${c.name}</div><div class="text-[11px] text-slate-500">${c.company}</div></td>
                <td class="py-3 px-4 font-mono text-slate-700">${c.phone}</td>
                <td class="py-3 px-4 text-slate-600">${c.email}</td>
                <td class="py-3 px-4 text-right">
                    <div class="flex items-center justify-end space-x-1.5">
                        <button onclick="editCustomer('${c.id}')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                        <button onclick="deleteCustomer('${c.id}')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                    </div>
                </td>
            `);
            tbody.append(tr);
        });
        lucide.createIcons();
    }

    function renderLogs() {
        const tbody = $('#log-table-body');
        tbody.empty();

        if (logs.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-8 text-slate-400">Belum ada riwayat log pesan pengingat.</td></tr>');
            return;
        }

        logs.forEach(function (l) {
            const badgeChan = l.channel === 'WhatsApp' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-sky-50 text-sky-700 border-sky-200';
            const tr = $('<tr class="hover:bg-slate-50/80 transition"></tr>');
            tr.html(`
                <td class="py-3 px-4 font-mono text-[11px] text-slate-500">${l.time}</td>
                <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold border ${badgeChan}">${l.channel}</span></td>
                <td class="py-3 px-4 font-medium text-slate-800">${l.recipient}</td>
                <td class="py-3 px-4 font-bold text-slate-900">${l.invoice}</td>
                <td class="py-3 px-4 text-slate-600 max-w-xs truncate" title="${l.message}">${l.message}</td>
                <td class="py-3 px-4 text-right"><span class="text-emerald-600 font-semibold text-[11px]">✓ Terkirim</span></td>
            `);
            tbody.append(tr);
        });
        lucide.createIcons();
    }

    function populateCustomerDropdown() {
        const select = $('#inv-customer-id');
        select.empty();
        customers.forEach(function (c) {
            select.append('<option value="' + c.id + '">' + c.name + ' (' + c.company + ')</option>');
        });
    }

    window.openModal = function (modalId) {
        $('#' + modalId).removeClass('hidden');
    }

    window.closeModal = function (modalId) {
        $('#' + modalId).addClass('hidden');
    }

    window.openAddInvoiceModal = function () {
        $('#invoice-modal-title').text('Buat Invoice Baru');
        $('#invoice-edit-id').val('');
        $('#form-invoice')[0].reset();
        const today = new Date().toISOString().split('T')[0];
        $('#inv-issue-date').val(today);
        $('#inv-due-date').val(today);
        openModal('modal-invoice');
    }

    window.editInvoice = function (id) {
        const inv = invoices.find(i => String(i.id) === String(id));
        if (!inv) return;
        $('#invoice-modal-title').text('Edit Invoice');
        $('#invoice-edit-id').val(inv.id);
        $('#inv-number').val(inv.number);
        $('#inv-customer-id').val(String(inv.customerId));
        $('#inv-amount').val(inv.amount);
        $('#inv-status').val(inv.status);
        $('#inv-issue-date').val(inv.issueDate);
        $('#inv-due-date').val(inv.dueDate);
        openModal('modal-invoice');
    }

    window.saveInvoice = function (e) {
        e.preventDefault();
        const editId = $('#invoice-edit-id').val();
        const data = {
            id: editId || 'inv_' + Date.now(),
            number: $('#inv-number').val(),
            customerId: $('#inv-customer-id').val(),
            amount: parseFloat($('#inv-amount').val()),
            status: $('#inv-status').val(),
            issueDate: $('#inv-issue-date').val(),
            dueDate: $('#inv-due-date').val(),
            remindersSent: editId ? (invoices.find(i => String(i.id) === String(editId))?.remindersSent || []) : []
        };

        const ajaxData = {
            action: 'ibs_save_invoice',
            nonce: window.ibsNonce,
            id: data.id,
            number: data.number,
            customerId: data.customerId,
            amount: data.amount,
            status: data.status,
            issueDate: data.issueDate,
            dueDate: data.dueDate,
        };

        $.post(ajaxurl, ajaxData, function () {
            if (editId) {
                const index = invoices.findIndex(i => String(i.id) === String(editId));
                if (index !== -1) invoices[index] = data;
                showToast('Invoice berhasil diperbarui');
            } else {
                invoices.unshift(data);
                showToast('Invoice baru berhasil dibuat');
            }
            closeModal('modal-invoice');
            updateDashboard();
            renderInvoices();
        });
    }

    window.deleteInvoice = function (id) {
        if (!confirm('Apakah Anda yakin ingin menghapus invoice ini?')) return;
        $.post(ajaxurl, { action: 'ibs_delete_invoice', nonce: window.ibsNonce, id }, function () {
            invoices = invoices.filter(i => String(i.id) !== String(id));
            updateDashboard();
            renderInvoices();
            showToast('Invoice berhasil dihapus');
        });
    }

    window.openAddCustomerModal = function () {
        $('#customer-modal-title').text('Tambah Customer Baru');
        $('#cust-edit-id').val('');
        $('#form-customer')[0].reset();
        openModal('modal-customer');
    }

    window.editCustomer = function (id) {
        const c = customers.find(item => String(item.id) === String(id));
        if (!c) return;
        $('#customer-modal-title').text('Edit Customer');
        $('#cust-edit-id').val(c.id);
        $('#cust-name').val(c.name);
        $('#cust-company').val(c.company);
        $('#cust-phone').val(c.phone);
        $('#cust-email').val(c.email);
        openModal('modal-customer');
    }

    window.saveCustomer = function (e) {
        e.preventDefault();
        const editId = $('#cust-edit-id').val();
        const data = {
            id: editId || 'c_' + Date.now(),
            name: $('#cust-name').val(),
            company: $('#cust-company').val(),
            phone: $('#cust-phone').val(),
            email: $('#cust-email').val(),
        };

        $.post(ajaxurl, {
            action: 'ibs_save_customer',
            nonce: window.ibsNonce,
            id: data.id,
            name: data.name,
            company: data.company,
            phone: data.phone,
            email: data.email,
        }, function () {
            if (editId) {
                const index = customers.findIndex(c => String(c.id) === String(editId));
                if (index !== -1) customers[index] = data;
                showToast('Data customer berhasil diperbarui');
            } else {
                customers.push(data);
                showToast('Customer baru berhasil ditambahkan');
            }
            closeModal('modal-customer');
            populateCustomerDropdown();
            renderCustomers();
            renderInvoices();
        });
    }

    window.deleteCustomer = function (id) {
        if (!confirm('Hapus customer ini? Invoice terkait mungkin kehilangan referensi.')) return;
        $.post(ajaxurl, { action: 'ibs_delete_customer', nonce: window.ibsNonce, id }, function () {
            customers = customers.filter(c => String(c.id) !== String(id));
            populateCustomerDropdown();
            renderCustomers();
            renderInvoices();
            showToast('Customer berhasil dihapus');
        });
    }

    window.openReminderSettingsModal = function () {
        $('#rem-h3').prop('checked', reminderConfig.h3);
        $('#rem-h1').prop('checked', reminderConfig.h1);
        $('#rem-h0').prop('checked', reminderConfig.h0);
        $('#rem-overdue').prop('checked', reminderConfig.overdue);
        $('#tpl-wa').val(reminderConfig.waTemplate);
        $('#tpl-email-subject').val(reminderConfig.emailSubject);
        $('#tpl-email-body').val(reminderConfig.emailBody);
        openModal('modal-reminder-settings');
    }

    window.saveReminderSettings = function () {
        reminderConfig.h3 = $('#rem-h3').is(':checked');
        reminderConfig.h1 = $('#rem-h1').is(':checked');
        reminderConfig.h0 = $('#rem-h0').is(':checked');
        reminderConfig.overdue = $('#rem-overdue').is(':checked');
        reminderConfig.waTemplate = $('#tpl-wa').val();
        reminderConfig.emailSubject = $('#tpl-email-subject').val();
        reminderConfig.emailBody = $('#tpl-email-body').val();
        closeModal('modal-reminder-settings');
        showToast('Konfigurasi pengingat otomatis disimpan');
    }

    window.openSimulator = function (channel, invoiceId) {
        const inv = invoices.find(i => String(i.id) === String(invoiceId));
        const cust = customers.find(c => String(c.id) === String(inv?.customerId)) || {};
        if (!inv) return;

        currentSimulation = { channel, invoice: inv, customer: cust };

        $('#sim-channel').text(channel);
        $('#sim-recipient').text(`${cust.name} (${cust.company})`);
        $('#sim-contact').text(channel === 'WhatsApp' ? cust.phone : cust.email);
        $('#sim-inv').text(inv.number);

        let compiledMsg = '';
        if (channel === 'WhatsApp') {
            compiledMsg = reminderConfig.waTemplate
                .replace('{nama}', cust.name || '')
                .replace('{perusahaan}', cust.company || '')
                .replace('{invoice_no}', inv.number || '')
                .replace('{nominal}', formatIDR(inv.amount || 0))
                .replace('{due_date}', inv.dueDate || '');
        } else {
            compiledMsg = `Subjek: ${reminderConfig.emailSubject.replace('{invoice_no}', inv.number || '')}\n\n` + reminderConfig.emailBody
                .replace('{nama}', cust.name || '')
                .replace('{perusahaan}', cust.company || '')
                .replace('{invoice_no}', inv.number || '')
                .replace('{nominal}', formatIDR(inv.amount || 0))
                .replace('{due_date}', inv.dueDate || '');
        }

        $('#sim-message').val(compiledMsg);
        $('#sim-loading').addClass('hidden');
        $('#sim-success').addClass('hidden');
        $('#btn-execute-send').prop('disabled', false).removeClass('opacity-50');
        openModal('modal-simulator');
    }

    window.executeSendMessage = function () {
        if (!currentSimulation) return;
        $('#sim-loading').removeClass('hidden');
        $('#btn-execute-send').prop('disabled', true).addClass('opacity-50');

        setTimeout(function () {
            $('#sim-loading').addClass('hidden');
            $('#sim-success').removeClass('hidden');

            const now = new Date();
            const timeStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0') + ' ' + String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

            logs.unshift({
                id: 'log_' + Date.now(),
                time: timeStr,
                channel: currentSimulation.channel,
                recipient: `${currentSimulation.customer.name} (${currentSimulation.customer.company})`,
                invoice: currentSimulation.invoice.number,
                message: $('#sim-message').val().substring(0, 80) + '...',
                status: 'Terkirim'
            });

            const invRef = invoices.find(i => String(i.id) === String(currentSimulation.invoice.id));
            if (invRef) {
                if (!invRef.remindersSent) invRef.remindersSent = [];
                const tag = currentSimulation.channel === 'WhatsApp' ? 'Manual WA' : 'Manual Email';
                if (!invRef.remindersSent.includes(tag)) invRef.remindersSent.push(tag);
            }

            renderLogs();
            renderInvoices();
            updateDashboard();
            showToast(`Berhasil mengirim pengingat ke ${currentSimulation.customer.name}`);

            $.post(ajaxurl, {
                action: 'ibs_send_message',
                nonce: window.ibsNonce,
                payload: {
                    channel: currentSimulation.channel,
                    recipient: `${currentSimulation.customer.name} (${currentSimulation.customer.company})`,
                    invoice: currentSimulation.invoice.number,
                    message: $('#sim-message').val()
                }
            });

            setTimeout(function () { closeModal('modal-simulator'); }, 1200);
        }, 1000);
    }

    window.triggerAutoCheckReminders = function () {
        let triggeredCount = 0;
        const todayStr = new Date().toISOString().split('T')[0];

        invoices.forEach(function (inv) {
            if (inv.status === 'Belum Lunas' || inv.status === 'Jatuh Tempo') {
                const due = new Date(inv.dueDate);
                const today = new Date(todayStr);
                const diffTime = due - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                let reminderTag = '';
                if (diffDays === 3 && reminderConfig.h3) reminderTag = 'H-3';
                else if (diffDays === 1 && reminderConfig.h1) reminderTag = 'H-1';
                else if (diffDays === 0 && reminderConfig.h0) reminderTag = 'Hari H';
                else if (diffDays < 0 && reminderConfig.overdue) reminderTag = 'Terlambat (H+)';

                if (reminderTag) {
                    if (!inv.remindersSent) inv.remindersSent = [];
                    if (!inv.remindersSent.includes(reminderTag)) {
                        inv.remindersSent.push(reminderTag);
                        triggeredCount++;

                        const cust = customers.find(c => String(c.id) === String(inv.customerId)) || { name: 'Customer', company: '-' };
                        logs.unshift({
                            id: 'cron_' + Date.now() + Math.random(),
                            time: todayStr + ' 08:00',
                            channel: 'WhatsApp & Email',
                            recipient: `${cust.name} (${cust.company})`,
                            invoice: inv.number,
                            message: `Automated reminder ${reminderTag} terkirim otomatis sistem.`,
                            status: 'Terkirim'
                        });
                    }
                }
            }
        });

        renderInvoices();
        renderLogs();
        updateDashboard();
        showToast(`Sistem memeriksa jatuh tempo: ${triggeredCount} pengingat otomatis dikirim.`);
    }

    window.clearLogs = function () {
        if (!confirm('Hapus seluruh riwayat log pengiriman?')) return;
        logs = [];
        renderLogs();
        updateDashboard();
        showToast('Log berhasil dibersihkan');
    }

    function showToast(msg) {
        const toast = $('#toast');
        $('#toast-msg').text(msg);
        toast.removeClass('translate-y-20 opacity-0').addClass('translate-y-0 opacity-100');
        setTimeout(function () {
            toast.removeClass('translate-y-0 opacity-100').addClass('translate-y-20 opacity-0');
        }, 3000);
    }

    window.ibsNonce = '<?php echo wp_create_nonce("ibs_nonce"); ?>';
})(jQuery);
