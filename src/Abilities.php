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
    public static $abilitiesShortCuts = [
        Abilities::OPEN => 'o',
        Abilities::OPEN_ENCRYPTED => 'O',
        Abilities::GET_COMMENT => 't',
        Abilities::EXTRACT_CONTENT => 'x',
        Abilities::STREAM_CONTENT => 's',
        Abilities::APPEND => 'a',
        Abilities::DELETE => 'd',
        Abilities::SET_COMMENT => 'T',
        Abilities::CREATE => 'c',
        Abilities::CREATE_ENCRYPTED => 'C',
        Abilities::CREATE_IN_STRING => 'S',
    ];
    public static $abilitiesLabels = [
        'open' => Abilities::OPEN,
        'open (+password)' => Abilities::OPEN_ENCRYPTED,
        'get comment' => Abilities::GET_COMMENT,
        'extract' => Abilities::EXTRACT_CONTENT,
        'stream' => Abilities::STREAM_CONTENT,
        'append' => Abilities::APPEND,
        'delete' => Abilities::DELETE,
        'set comment' => Abilities::SET_COMMENT,
        'create' => Abilities::CREATE,
        'create (+password)' => Abilities::CREATE_ENCRYPTED,
        'create (as string)' => Abilities::CREATE_IN_STRING,
    ];
}
