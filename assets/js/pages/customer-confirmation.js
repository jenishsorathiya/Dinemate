(function () {
    if (typeof confetti === 'function') {
        confetti({
            particleCount: 36,
            spread: 54,
            scalar: 0.72,
            origin: { y: 0.18 },
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
