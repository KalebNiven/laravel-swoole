<?php

namespace SwooleTW\Http\Tests\HotReload;

use Carbon\Carbon;
use SwooleTW\Http\HotReload\FSEvent;
use SwooleTW\Http\HotReload\FSEventParser;
use SwooleTW\Http\Tests\TestCase;

/**
 * Class FSEventParserTest
 */
class FSEventParserTest extends TestCase
{
    public function testItCanCreateObjectAfterParse()
    {
        $buffer = 'Mon Dec 31 01:18:34 2018 /Some/Path/To/File/File.php Renamed OwnerModified IsFile';
        $event = FSEventParser::toEvent($buffer);

        $this->assertInstanceOf(FSEvent::class, $event);
    }
}