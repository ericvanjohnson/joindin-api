<?php

namespace Joindin\Api\Test\Service;

use Joindin\Api\Service\NullSpamCheckService;
use PHPUnit\Framework\TestCase;

final class NullSpamCheckServiceTest extends TestCase
{
    public function testSpamCheckShouldReturnTrue(): void
    {
        $service = new NullSpamCheckService();
        $this->assertTrue($service->isCommentAcceptable('foo bar', '0.0.0.0', 'userAgent'));
    }
}
