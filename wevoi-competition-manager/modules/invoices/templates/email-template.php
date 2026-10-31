<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Invoice Email</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            border-top: 4px solid #007bff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            color: #999;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
        }
        .info-box {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice from WeVoi</h1>
        </div>
        
        <div class="content">
            <p>Dear {recipient_name},</p>
            
            <p>Please find attached your invoice {invoice_number} dated {invoice_date} for the amount of {total_amount}.</p>
            
            <p>This invoice is for {competition_name} Coupon(s) with code {coupon_code}.</p>
            
            <div class="info-box">
                <h3>Payment Details:</h3>
                <p>
                    <strong>Account Name:</strong> WeVoi Ltd<br>
                    <strong>Bank:</strong> Example Bank<br>
                    <strong>Account Number:</strong> 12345678<br>
                    <strong>Sort Code:</strong> 12-34-56<br>
                    <strong>IBAN:</strong> GB00EXMP12345678
                </p>
                <p>Please include the invoice number as a reference when making payment.</p>
            </div>
            
            <p>If you have any questions regarding this invoice, please feel free to contact us at financial@wevoi.com.</p>
            
            <p>Thank you for your business!</p>
        </div>
        
        <div class="footer">
            <p>WeVoi Ltd, 123 WeVoi Street, London, United Kingdom</p>
            <p>&copy; <?php echo date('Y'); ?> WeVoi Ltd. All rights reserved.</p>
        </div>
    </div>
</body>
</html>