<?php

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName

// turn '123.4' and '56.78' into '12340' and '05678'
function scanpay_dighomogenize(string $a, string $b): array {
    $h       = [];
    $h['as'] = (substr($a, 0, 1) === '-');
    $h['bs'] = (substr($b, 0, 1) === '-');
    $aa      = explode('.', (($h['as']) ? substr($a, 1) : $a) . '.'); // guarantee 2 elems
    $bb      = explode('.', (($h['bs']) ? substr($b, 1) : $b) . '.');
    $h['il'] = max(strlen($aa[0]), strlen($bb[0]));
    $h['fl'] = max(strlen($aa[1]), strlen($bb[1]));
    $h['a']  = str_pad($aa[0], $h['il'], '0', STR_PAD_LEFT) . str_pad($aa[1], $h['fl'], '0');
    $h['b']  = str_pad($bb[0], $h['il'], '0', STR_PAD_LEFT) . str_pad($bb[1], $h['fl'], '0');
    return $h;
}

// turn '012340' into '123.40' or '12300' into '123'
function scanpay_digformat(bool $sign, string $s, int $fl): string {
    $il = strlen($s) - $fl;
    $s  = ltrim(substr($s, 0, $il), '0') . '.' . substr($s, $il);
    if ('' === $s || '.' === $s[0]) {
        $s = '0' . $s;
    }
    for ($d = strlen($s) - 1; $d > 0 && '0' === $s[$d]; $d--);
    return (($sign) ? '-' : '') . (('.' === $s[$d]) ? substr($s, 0, $d) : $s);
}

function scanpay_digadd(string $a, string $b): string {
    for ($s = '', $rem = 0, $i = strlen($a) - 1; $i >= 0; $i--) {
        $r = intval($a[$i]) + intval($b[$i]) + $rem;
        if ($r >= 10) {
            $r  -= 10;
            $rem = 1;
        } else {
            $rem = 0;
        }
        $s[$i] = strval($r);
    }
    return ($rem > 0) ? strval($rem) . $s : $s;
}

function scanpay_digsub(string $a, string $b): string {
    for ($s = '', $rem = 0, $i = strlen($a) - 1; $i >= 0; $i--) {
        if ($a[$i] < $b[$i] + $rem) {
            $s[$i] = 10 + $a[$i] - ($b[$i] + $rem);
            $rem   = 1;
        } else {
            $s[$i] = $a[$i] - ($b[$i] + $rem);
            $rem   = 0;
        }
    }
    return $s;
}

function scanpay_addmoney(string $a, string $b): string {
    $h = scanpay_dighomogenize($a, $b);
    // sign magic to avoid subtracting a larger number from a smaller one
    if ($h['as'] !== $h['bs']) {
        if (strcmp($h['a'], $h['b']) < 0) {
            $s       = scanpay_digsub($h['b'], $h['a']);
            $h['as'] = !$h['as'];
        } else {
            $s = scanpay_digsub($h['a'], $h['b']);
        }
    } else {
        $s = scanpay_digadd($h['a'], $h['b']);
    }
    return scanpay_digformat($h['as'], $s, $h['fl']);
}

function scanpay_submoney(string $a, string $b): string {
    // a - b ≡ a + (-b)
    return scanpay_addmoney($a, (substr($b, 0, 1) === '-') ? substr($b, 1) : ('-' . $b));
}

function scanpay_cmpmoney(string $a, string $b): int {
    $h = scanpay_dighomogenize($a, $b);
    if ($h['as'] && $h['bs']) {
        return strcmp($h['b'], $h['a']);
    }
    if ($h['as']) {
        return -1;
    }
    if ($h['bs']) {
        return 1;
    }
    return strcmp($h['a'], $h['b']);
}
