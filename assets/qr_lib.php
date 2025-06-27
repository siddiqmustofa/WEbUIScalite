<?php
function generateQRCodeURL($data) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($data);
}
