<?php
/**
 * Generate JWT keypair using phpseclib (works when PHP OpenSSL fails on Windows).
 * Run from project root: php generate_jwt_keys.php
 */

require __DIR__ . '/vendor/autoload.php';

use phpseclib3\Crypt\RSA;

$projectDir = __DIR__;
$envFile = $projectDir . '/.env';
$jwtDir = $projectDir . '/config/jwt';
$privatePath = $jwtDir . '/private.pem';
$publicPath = $jwtDir . '/public.pem';

// Read JWT_PASSPHRASE from .env
$passphrase = null;
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'JWT_PASSPHRASE=') === 0) {
            $passphrase = trim(substr($line, strlen('JWT_PASSPHRASE=')));
            $passphrase = trim($passphrase, '"\'');
            break;
        }
    }
}
if ($passphrase === null) {
    fwrite(STDERR, "JWT_PASSPHRASE not found in .env\n");
    exit(1);
}

if (!is_dir($jwtDir)) {
    mkdir($jwtDir, 0755, true);
}

// Generate RSA key pair with phpseclib (no PHP OpenSSL extension needed for keygen)
$private = RSA::createKey(2048);
$public = $private->getPublicKey();

// Export private key with passphrase (PKCS8 encrypted)
$privatePem = $private->withPassword($passphrase)->toString('PKCS8');
// Export public key
$publicPem = $public->toString('PKCS8');

file_put_contents($privatePath, $privatePem);
file_put_contents($publicPath, $publicPem);

echo "JWT keypair written to:\n  $privatePath\n  $publicPath\n";
