/**
 * Invoice Module Scripts
 * 
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const previewButton = document.getElementById('preview-invoice');
    const sendButton = document.getElementById('send-invoice');
    const confirmSendButton = document.getElementById('confirm-send');
    const detailSendButton = document.getElementById('detail-send-invoice');
    const detailMarkPaidButton = document.getElementById('detail-mark-paid');
    const markPaidButtons = document.querySelectorAll('.mark-paid');
    const sendInvoiceButtons = document.querySelectorAll('.send-invoice');
    const deleteInvoiceLinks = document.querySelectorAll('.delete-invoice');
    const confirmPaymentButton = document.getElementById('confirm-payment');
    const previewModal = document.getElementById('preview-modal');
    const paymentModal = document.getElementById('payment-modal');
    const modalCloseButtons = document.querySelectorAll('.invoice-modal-close');
    const unitPriceInput = document.getElementById('unit_price');
    const quantityInput = document.getElementById('quantity');
    const totalAmountSpan = document.getElementById('total_amount');
    
    // Show preview modal
    if (previewButton) {
        previewButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Validate form
            if (!validateInvoiceForm()) {
                return;
            }
            
            // Show modal with loading message
            previewModal.style.display = 'block';
            const previewFrame = document.getElementById('preview-frame');
            previewFrame.src = '';
            previewFrame.contentDocument.body.innerHTML = '<div style="text-align:center;padding:20px;">Loading preview...</div>';
            
            // Get form data
            const formData = new FormData(document.getElementById('invoice-form'));
            formData.append('action', 'preview_invoice');
            formData.append('nonce', wevoiInvoice.nonce);
            
            // AJAX request
            fetch(wevoiInvoice.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    previewFrame.src = data.data.preview_url;
                } else {
                    alert(data.data || wevoiInvoice.strings.invoice_error);
                    previewModal.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(wevoiInvoice.strings.invoice_error);
                previewModal.style.display = 'none';
            });
        });
    }
    
    // Send invoice directly
    if (sendButton) {
        sendButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Validate form
            if (!validateInvoiceForm()) {
                return;
            }
            
            // Show spinner
            const spinner = document.querySelector('.invoice-form-actions .spinner');
            spinner.style.visibility = 'visible';
            
            // Disable buttons
            sendButton.disabled = true;
            previewButton.disabled = true;
            
            // Get form data
            const formData = new FormData(document.getElementById('invoice-form'));
            formData.append('action', 'send_invoice');
            formData.append('nonce', wevoiInvoice.nonce);
            
            // AJAX request
            fetch(wevoiInvoice.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.data.warning) {
                        // Show warning but continue with success flow
                        alert(data.data.message);
                        window.location.href = `${ajaxurl.replace('admin-ajax.php', 'admin.php')}?page=wevoi-competitions&tab=invoices&message=warning`;
                    } else {
                        // Redirect to invoice list
                        window.location.href = `${ajaxurl.replace('admin-ajax.php', 'admin.php')}?page=wevoi-competitions&tab=invoices&message=sent`;
                    }
                } else {
                    // Show error and reset form
                    alert(data.data || wevoiInvoice.strings.invoice_error);
                    spinner.style.visibility = 'hidden';
                    sendButton.disabled = false;
                    previewButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(wevoiInvoice.strings.invoice_error);
                spinner.style.visibility = 'hidden';
                sendButton.disabled = false;
                previewButton.disabled = false;
            });
        });
    }
    
    // Send invoice from preview modal
    if (confirmSendButton) {
        confirmSendButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Show spinner
            const spinner = document.querySelector('.invoice-form-actions .spinner');
            spinner.style.visibility = 'visible';
            
            // Disable buttons
            confirmSendButton.disabled = true;
            const closeButtons = document.querySelectorAll('.invoice-modal-close');
            closeButtons.forEach(button => button.disabled = true);
            
            // Get form data
            const formData = new FormData(document.getElementById('invoice-form'));
            formData.append('action', 'send_invoice');
            formData.append('nonce', wevoiInvoice.nonce);
            
            // AJAX request
            fetch(wevoiInvoice.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to invoice list
                    window.location.href = `${ajaxurl.replace('admin-ajax.php', 'admin.php')}?page=wevoi-competitions&tab=invoices&message=sent`;
                } else {
                    // Show error and reset form
                    alert(data.data || wevoiInvoice.strings.invoice_error);
                    spinner.style.visibility = 'hidden';
                    confirmSendButton.disabled = false;
                    closeButtons.forEach(button => button.disabled = false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(wevoiInvoice.strings.invoice_error);
                spinner.style.visibility = 'hidden';
                confirmSendButton.disabled = false;
                closeButtons.forEach(button => button.disabled = false);
            });
        });
    }
    
    // Send invoice from detail page
    if (detailSendButton) {
        detailSendButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            if (!confirm(wevoiInvoice.strings.confirm_send)) {
                return;
            }
            
            // Disable button
            detailSendButton.disabled = true;
            
            // Get invoice ID
            const invoiceId = detailSendButton.dataset.id;
            
            // AJAX request
            const formData = new FormData();
            formData.append('action', 'update_invoice_status');
            formData.append('nonce', wevoiInvoice.nonce);
            formData.append('invoice_id', invoiceId);
            formData.append('status', 'SENT');
            
            fetch(wevoiInvoice.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page
                    window.location.reload();
                } else {
                    // Show error and reset form
                    alert(data.data || wevoiInvoice.strings.invoice_error);
                    detailSendButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(wevoiInvoice.strings.invoice_error);
                detailSendButton.disabled = false;
            });
        });
    }
    
    // Mark invoice as paid from detail page
    if (detailMarkPaidButton) {
        detailMarkPaidButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Show payment modal
            document.getElementById('invoice_id').value = detailMarkPaidButton.dataset.id;
            paymentModal.style.display = 'block';
        });
    }
    
    // Mark invoice as paid from list
    if (markPaidButtons.length > 0) {
        markPaidButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Show payment modal
                document.getElementById('invoice_id').value = button.dataset.id;
                paymentModal.style.display = 'block';
            });
        });
    }
    
    // Send invoice from list
    if (sendInvoiceButtons.length > 0) {
        sendInvoiceButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                if (!confirm(wevoiInvoice.strings.confirm_send)) {
                    return;
                }
                
                // Disable button
                button.disabled = true;
                
                // Get invoice ID
                const invoiceId = button.dataset.id;
                
                // AJAX request
                const formData = new FormData();
                formData.append('action', 'update_invoice_status');
                formData.append('nonce', wevoiInvoice.nonce);
                formData.append('invoice_id', invoiceId);
                formData.append('status', 'SENT');
                
                fetch(wevoiInvoice.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page
                        window.location.reload();
                    } else {
                        // Show error and reset form
                        alert(data.data || wevoiInvoice.strings.invoice_error);
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(wevoiInvoice.strings.invoice_error);
                    button.disabled = false;
                });
            });
        });
    }
    
    // Delete invoice
    if (deleteInvoiceLinks.length > 0) {
        deleteInvoiceLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (!confirm(wevoiInvoice.strings.confirm_delete)) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // Confirm payment
    if (confirmPaymentButton) {
        confirmPaymentButton.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Disable button
            confirmPaymentButton.disabled = true;
            
            // Get form data
            const invoiceId = document.getElementById('invoice_id').value;
            const paymentMethod = document.getElementById('payment_method').value;
            const paymentReference = document.getElementById('payment_reference').value;
            const paymentDate = document.getElementById('payment_date').value;
            
            // Validate data
            if (!paymentMethod || !paymentDate) {
                alert(wevoiInvoice.strings.required_fields);
                confirmPaymentButton.disabled = false;
                return;
            }
            
            // AJAX request
            const formData = new FormData();
            formData.append('action', 'update_invoice_status');
            formData.append('nonce', wevoiInvoice.nonce);
            formData.append('invoice_id', invoiceId);
            formData.append('status', 'PAID');
            formData.append('payment_data[payment_method]', paymentMethod);
            formData.append('payment_data[payment_reference]', paymentReference);
            formData.append('payment_data[payment_date]', paymentDate);
            
            fetch(wevoiInvoice.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide modal and reload page
                    paymentModal.style.display = 'none';
                    window.location.reload();
                } else {
                    // Show error and reset form
                    alert(data.data || wevoiInvoice.strings.invoice_error);
                    confirmPaymentButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(wevoiInvoice.strings.invoice_error);
                confirmPaymentButton.disabled = false;
            });
        });
    }
    
    // Close modals
    if (modalCloseButtons.length > 0) {
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Get parent modal
                const modal = button.closest('.invoice-modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('invoice-modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Calculate total amount
    if (unitPriceInput && quantityInput && totalAmountSpan) {
        const calculateTotal = () => {
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const total = unitPrice * quantity;
            totalAmountSpan.textContent = total.toFixed(2);
        };
        
        unitPriceInput.addEventListener('input', calculateTotal);
        quantityInput.addEventListener('input', calculateTotal);
    }
    
    // Validate invoice form
    function validateInvoiceForm() {
        const form = document.getElementById('invoice-form');
        
        if (!form) {
            return false;
        }
        
        const recipientName = form.querySelector('#recipient_name');
        const recipientEmail = form.querySelector('#recipient_email');
        const unitPrice = form.querySelector('#unit_price');
        const quantity = form.querySelector('#quantity');
        
        let isValid = true;
        
        // Validate required fields
        if (!recipientName.value.trim()) {
            recipientName.classList.add('error');
            isValid = false;
        } else {
            recipientName.classList.remove('error');
        }
        
        if (!recipientEmail.value.trim() || !validateEmail(recipientEmail.value)) {
            recipientEmail.classList.add('error');
            isValid = false;
        } else {
            recipientEmail.classList.remove('error');
        }
        
        if (!unitPrice.value || parseFloat(unitPrice.value) <= 0) {
            unitPrice.classList.add('error');
            isValid = false;
        } else {
            unitPrice.classList.remove('error');
        }
        
        if (!quantity.value || parseInt(quantity.value) <= 0) {
            quantity.classList.add('error');
            isValid = false;
        } else {
            quantity.classList.remove('error');
        }
        
        if (!isValid) {
            alert('Please fill in all required fields correctly.');
        }
        
        return isValid;
    }
    
    // Validate email
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});