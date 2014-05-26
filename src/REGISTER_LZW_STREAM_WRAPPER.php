<?php
namespace wapmorgan\UnifiedArchive;

stream_wrapper_register('compress.lzw', __NAMESPACE__.'\\LzwStreamWrapper');
