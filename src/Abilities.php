<?php

namespace wapmorgan\UnifiedArchive;

class Abilities
{
    const OPEN = 1;
    const OPEN_ENCRYPTED = 2;
    const OPEN_VOLUMED = 4;
    const GET_COMMENT = 64;
    const EXTRACT_CONTENT = 128;
    const STREAM_CONTENT = 256;
    const SET_COMMENT = 16384;
    const APPEND = 4096;
    const DELETE = 8192;
    const CREATE = 1048576;
    const CREATE_ENCRYPTED = 2097152;
    const CREATE_IN_STRING = 4194304;
}
