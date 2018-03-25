<?php
/**
 * @param string $file GZipped file
 * @return array|false Array with 'mtime' and 'size' items
 */
function gzip_stat($file)
{
    $fp = fopen($file, 'rb');
    if (filesize($file) < 18 || strcmp(fread($fp, 2), "\x1f\x8b")) {
        return false;  // Not GZIP format (See RFC 1952)
    }
    $method = fread($fp, 1);
    $flags = fread($fp, 1);
    $stat = unpack('Vmtime', fread($fp, 4));
    fseek($fp, -4, SEEK_END);
    $stat += unpack('Vsize', fread($fp, 4));
    fclose($fp);

    return $stat;
}
