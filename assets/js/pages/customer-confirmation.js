(function () {
    if (typeof confetti === 'function') {
        confetti({
            particleCount: 120,
            spread: 70,
            origin: { y: 0.6 },
        });
    }

    const qrElement = document.getElementById('qr');
    if (qrElement && typeof QRCode === 'function') {
        new QRCode(qrElement, {
            text: qrElement.dataset.reservationQr || 'Reservation',
            width: 120,
            height: 120,
        });
    }
})();
