export const generatePaymentStatusHTML = (data) => {
  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Payment Status</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          background-color: #f5f5f5;
          padding: 20px;
        }
        .payment-status {
          background-color: #fff;
          padding: 20px;
          border-radius: 5px;
          box-shadow: 1px 4px 26px 7px #dcdcdc;
          width: max-content;
          margin: 0 auto;
        }
        table {
          width: 100%;
          border-collapse: collapse;
        }
        th, td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: left;
        }
        th {
          background-color: #f2f2f2;
        }
        /* Add more styles as needed */
      </style>
    </head>
    <body>
      <div className="payment-status">
        <h2>Payment Status</h2>
        <table>
          <tr>
            <th>Field</th>
            <th>Value</th>
          </tr>
          <tr>
            <td><strong>Transaction ID</strong></td>
            <td>${data?.txn_id}</td>
          </tr>
          <tr>
            <td><strong>Status</strong></td>
            <td>${data?.resp_message}</td>
          </tr>
          <tr>
            <td><strong>Payment Mode</strong></td>
            <td>${data?.payment_mode}</td>
          </tr>
          <tr>
            <td><strong>Bank Reference ID</strong></td>
            <td>${data?.bank_ref_id}</td>
          </tr>
          <tr>
            <td><strong>Customer Email</strong></td>
            <td>${data?.cust_email_id}</td>
          </tr>
          <tr>
            <td><strong>Customer Phone</strong></td>
            <td>${data?.cust_mobile_no}</td>
          </tr>
          <tr>
            <td><strong>Merchant ID</strong></td>
            <td>${data?.merchant_id}</td>
          </tr>
          <tr>
            <td><strong>Transaction Date</strong></td>
            <td>${data.txn_date_time}</td>
          </tr>
          <tr>
            <td><strong>Amount</strong></td>
            <td>₹ ${data?.txn_amount}</td>
          </tr>
        </table>
      </div>
    </body>
    </html>
    
    `;
};
