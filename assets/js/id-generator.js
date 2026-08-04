// ============================================================
// BodaERP - ID Card Generator
// ============================================================

function generateIDCard(riderData) {
    const idHTML = `
        <div class="id-card" id="idCardFront">
            <div class="id-header">
                <div class="id-header-top" style="display:flex;align-items:center;gap:10px;">
                    <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:2px solid #0a2d6e;flex-shrink:0;background:white;display:flex;align-items:center;justify-content:center;">
                        <img src="assets/images/lcc.png" alt="LCC" style="width:100%;height:100%;object-fit:contain;"
                             onerror="this.style.display='none'">
                    </div>
                    <div>
                        <div style="font-weight:900;color:#0a2d6e;font-size:13px;letter-spacing:1px;text-transform:uppercase;">LIRA CITY COUNCIL</div>
                        <div style="font-size:7px;color:#6c757d;letter-spacing:2px;font-weight:600;text-transform:uppercase;">BODA BODA OPERATOR ID CARD</div>
                    </div>
                    <div class="id-header-year" style="margin-left:auto;">2026 / 2027</div>
                </div>
            </div>
            
            <div class="id-body">
                <div class="id-photo">
                    <img src="${riderData.photo || 'assets/images/default.jpg'}" alt="Photo"
                         onerror="this.src='assets/images/avatar-placeholder.png'">
                </div>
                <div class="id-details">
                    <p><strong>Name:</strong> ${riderData.fullName}</p>
                    <p><strong>NIN:</strong> ${riderData.nin}</p>
                    <p><strong>Stage:</strong> ${riderData.stage}</p>
                    <p><strong>Route:</strong> ${riderData.route}</p>
                    <p><strong>Bike:</strong> ${riderData.bikePlate}</p>
                    <p><strong>Status:</strong> <span class="status active">● ACTIVE</span></p>
                </div>
            </div>
            
            <div class="id-footer">
                <div class="id-qr">
                    <div class="qr-placeholder">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="id-expiry">
                        <small>EXPIRY DATE</small>
                        <strong>${riderData.expiryDate}</strong>
                    </div>
                </div>
                <div class="id-meta">
                    <span>ID: ${riderData.idNumber}</span>
                    <span>Member Since: ${riderData.memberSince}</span>
                </div>
            </div>
        </div>
    `;
    
    return idHTML;
}

// ----- Generate PDF (Using html2pdf or similar) -----
function downloadIDCardPDF(riderData) {
    // In production, use html2pdf.js or jsPDF
    // This is a placeholder function
    
    showToast('Generating PDF...', 'info');
    
    // Simulate PDF generation
    setTimeout(() => {
        showToast('ID Card PDF downloaded successfully!', 'success');
    }, 1500);
}

// ----- Print ID Card -----
function printIDCard() {
    const printWindow = window.open('', '_blank');
    const idCard = document.querySelector('.id-card-container')?.innerHTML;
    
    if (idCard) {
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print ID Card</title>
                    <style>
                        body { margin: 0; padding: 20px; }
                        .id-card-container { 
                            width: 85.6mm; 
                            margin: 0 auto;
                        }
                        @media print {
                            body { padding: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                    <link rel="stylesheet" href="../../assets/css/style.css">
                </head>
                <body>
                    <div class="id-card-container">${idCard}</div>
                    <script>
                        window.onload = function() {
                            window.print();
                            window.close();
                        };
                    <\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    } else {
        showToast('No ID card found to print.', 'error');
    }
}