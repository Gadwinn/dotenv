<?php

declare(strict_types=1);

namespace G\Dotenv\Tests;

use PHPUnit\Framework\TestCase;
use G\Dotenv\{Dotenv};
use RuntimeException;

final class DotenvTest extends TestCase
{
    protected string $envFile = __DIR__ . '/.env.test';

    public function testLoad()
    {
        $dotenv = new Dotenv();
        $dotenv = $dotenv->load('tests/.env', 'tests/.env.${APP_ENV}');

        $this->assertTrue($dotenv->has('KEY_TEST'));
        $this->assertTrue($dotenv->has('KEY_WITH_SPACES'));
        $this->assertFalse($dotenv->has('KEY_DOES_NOT_EXIST'));
        $this->assertFalse($dotenv->has('export KEY_EXPORTED'));

        $this->assertSame('VALUE', $dotenv->get('KEY_TEST'));
        $this->assertSame('VALUE WITH SPACES', $dotenv->get('KEY_WITH_SPACES'));
        $this->assertSame('default', $dotenv->get('KEY_DOES_NOT_EXIST', 'default'));

        $this->assertSame('value', $dotenv->get('key_with_quotes'));
        $this->assertSame('value', $dotenv->get('key_with_double_quotes'));
        $this->assertSame('value=with=equals=sign', $dotenv->get('key_with_equals_sign'));
        $this->assertSame('value#with#hash', $dotenv->get('key_with_hash'));
        $this->assertSame('value', $dotenv->get('key_with_comment'));
        $this->assertSame('value', $dotenv->get('key_with_quotes_and_comment'));
        $this->assertSame("value\non\n3 lines", $dotenv->get('key_on_3_lines'));
        $this->assertSame('', $dotenv->get('key_without_value', 'default'));
        $this->assertSame('', $dotenv->get('key_empty_value', 'default'));
        $this->assertSame('value', $dotenv->get('KEY_EXPORTED'));
    }

    // public function testLoadFromData()
    // {
    //     $dotenv = (new Dotenv())->loadFromData([
    //         'FOO' => 'BAR',
    //         'BAR' => 'BAZ',
    //     ]);

    //     $this->assertTrue($dotenv->has('FOO'));
    //     $this->assertTrue($dotenv->has('BAR'));
    //     $this->assertFalse($dotenv->has('BAZ'));

    //     $this->assertSame('BAR', $dotenv->get('FOO'));
    //     $this->assertSame('BAZ', $dotenv->get('BAR'));
    //     $this->assertSame('Value does not exist', $dotenv->get('BAZ', 'Value does not exist'));
    // }

    // public function testLoadFromEnv()
    // {
    //     $dotenv = (new Dotenv())->loadFromEnv();

    //     $this->assertTrue($dotenv->has('FOO'));
    //     $this->assertTrue($dotenv->has('BAR'));
    //     $this->assertFalse($dotenv->has('BAZ'));

    //     $this->assertSame('BAR', $dotenv->get('FOO'));
    //     $this->assertSame('BAZ', $dotenv->get('BAR'));
    //     $this->assertSame('', $dotenv->get('BAZ'));
    // }

    public function testLoadWithInvalidPath()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found: ' . __DIR__ . '/.env.invalid');
        $dotenv = new Dotenv([Dotenv::OPT_STRICT_FILES => true]);
        $dotenv = $dotenv->load(__DIR__ . '/.env.invalid');
    }

    // public function testLoadWithUnreadableFile()
    // {
    //     if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    //         $this->markTestSkipped('This test cannot run on Windows.');
    //     }

    //     $this->markTestIncomplete('Need to find a way to test this');
    //     // touch(__DIR__ . '/.env.unreadable');
    //     // $this->handle = fopen(__DIR__ . '/.env.unreadable', 'w');
    //     // flock($this->handle, LOCK_EX);
    //     // fwrite($this->handle, 'FOO=BAR');
    //     // chmod(__DIR__ . '/.env.unreadable', 0o000);

    //     // $this->assertIsNotReadable(__DIR__ . '/.env.unreadable', 'File should not be readable');
    //     // $this->expectException(RuntimeException::class);
    //     // $this->expectExceptionMessage('File not readable: ' . __DIR__ . '/.env.unreadable');
    //     // (new Dotenv())->load(__DIR__ . '/.env.unreadable');
    // }

    public function testStore()
    {
        $dotenv = new Dotenv([Dotenv::OPT_UNSAFE => true]);
        $dotenv = $dotenv->load($this->envFile);

        $dotenv->store();

        $this->assertSame('VALUE', getenv('KEY_TEST'));
        $this->assertSame('VALUE', $_ENV['KEY_TEST']);
    }

    public function testGetValues()
    {
        $dotenv = new Dotenv();
        $dotenv = $dotenv->load($this->envFile);

        $this->assertContains('value', $dotenv->getValues());
    }

    public function testWithValue()
    {
        $dotenv = new Dotenv();
        $dotenv = $dotenv->load($this->envFile);

        $dotenv = $dotenv->withValue('BAZ', 'FOO');

        $this->assertTrue($dotenv->has('KEY_TEST'));
        $this->assertTrue($dotenv->has('BAZ'));

        $this->assertSame('VALUE', $dotenv->get('KEY_TEST'));
        $this->assertSame('FOO', $dotenv->get('BAZ'));
    }

    public function testWithoutValue()
    {
        $dotenv = new Dotenv([Dotenv::OPT_UNSAFE => true]);
        $dotenv = $dotenv->load($this->envFile);

        $this->assertTrue($dotenv->has('KEY_WITH_SPACES'));

        $dotenv = $dotenv->withoutValue('KEY_WITH_SPACES');

        // for code coverage
        $dotenv->store();

        $this->assertTrue($dotenv->has('KEY_TEST'));
        $this->assertFalse($dotenv->has('KEY_WITH_SPACES'));

        $this->assertSame('VALUE', $dotenv->get('KEY_TEST'));
        $this->assertSame('default', $dotenv->get('KEY_WITH_SPACES', 'default'));
    }

    public function testInvalidKey()
    {
        $this->expectException(RuntimeException::class);

        (new Dotenv())->withValue('INVALID KEY', 'VALUE');
    }

    protected function tearDown(): void
    {
        foreach (['KEY_TEST', 'KEY_WITH_SPACES', 'DOTENV_T_A', 'DOTENV_T_ZERO'] as $key) {
            unset($_ENV[$key]);
            putenv($key);
        }
    }

    // -------------------------------------------------------------------------
    // Syntax
    // -------------------------------------------------------------------------

    public function testHashInsideAnUnquotedValueIsNotAComment(): void
    {
        $dotenv = (new Dotenv())->load("PASSWORD=abc#123\nCOLOR=#fff\nNAME=value # comment\nQUOTED='value'# comment");

        $this->assertSame('abc#123', $dotenv->get('PASSWORD'));
        $this->assertSame('', $dotenv->get('COLOR', 'default'));
        $this->assertSame('value', $dotenv->get('NAME'));
        $this->assertSame('value', $dotenv->get('QUOTED'));
    }

    public function testWindowsLineEndings(): void
    {
        $dotenv = (new Dotenv())->load("A=\"quoted\"\r\nB=plain\r\nC='x' # comment\r\nD=\"\"\"\r\nl1\r\nl2\r\n\"\"\"\r\n");

        $this->assertSame(['A' => 'quoted', 'B' => 'plain', 'C' => 'x', 'D' => "l1\nl2"], $dotenv->getValues());
    }

    public function testExportKeywordNeedsASpace(): void
    {
        $dotenv = (new Dotenv())->load("export A=1\nexportB=2");

        $this->assertSame(['A' => '1', 'exportB' => '2'], $dotenv->getValues());
    }

    public function testQuotedValueKeepsItsSpaces(): void
    {
        $dotenv = (new Dotenv())->load("DOTENV_T_A=\"  padded  \"");
        $dotenv->store();

        $this->assertSame('  padded  ', $dotenv->get('DOTENV_T_A'));
        $this->assertSame('  padded  ', $_ENV['DOTENV_T_A']);
    }

    public function testLastDefinitionWins(): void
    {
        $this->assertSame('2', (new Dotenv())->load('A=1', 'A=2')->get('A'));
    }

    // -------------------------------------------------------------------------
    // Interpolation
    // -------------------------------------------------------------------------

    public function testInterpolationUsesLoadedValues(): void
    {
        $dotenv = (new Dotenv())->load('NAME=world', 'GREETING=hello ${NAME}', 'OTHER=${UNKNOWN_DOTENV_KEY}');

        $this->assertSame('hello world', $dotenv->get('GREETING'));
        $this->assertSame('${UNKNOWN_DOTENV_KEY}', $dotenv->get('OTHER'));
    }

    public function testInterpolationAcceptsTheValueZero(): void
    {
        putenv('DOTENV_T_ZERO=0');

        $this->assertSame('0', (new Dotenv())->load('A=${DOTENV_T_ZERO}')->get('A'));
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    public function testMissingFileIsSkippedByDefault(): void
    {
        $dotenv = (new Dotenv())->load(__DIR__ . '/.env.invalid', __DIR__ . '/.env');

        $this->assertSame('test', $dotenv->get('APP_ENV'));
    }

    public function testGlobPattern(): void
    {
        $dotenv = (new Dotenv())->load(__DIR__ . '/.env.t*');

        $this->assertSame('VALUE', $dotenv->get('KEY_TEST'));
    }

    public function testWithoutCascadeOnlyTheFirstFileIsLoaded(): void
    {
        $dotenv = (new Dotenv([Dotenv::OPT_CASCADE_FILES => false]))->load(
            __DIR__ . '/.env.invalid',
            __DIR__ . '/.env',
            $this->envFile
        );

        $this->assertTrue($dotenv->has('APP_ENV'));
        $this->assertFalse($dotenv->has('KEY_TEST'));
    }

    public function testWithoutCascadeAGlobCountsAsTheFirstFile(): void
    {
        $dotenv = (new Dotenv([Dotenv::OPT_CASCADE_FILES => false]))->load(__DIR__ . '/.env.t*', __DIR__ . '/.env');

        $this->assertTrue($dotenv->has('KEY_TEST'));
        $this->assertFalse($dotenv->has('APP_ENV'));
    }

    // -------------------------------------------------------------------------
    // Immutability and storage
    // -------------------------------------------------------------------------

    public function testLoadAndMutationsLeaveTheOriginalUntouched(): void
    {
        $empty = new Dotenv();
        $loaded = $empty->load('A=1');
        $extended = $loaded->withValue('B', '2');
        $reduced = $loaded->withoutValue('A');

        $this->assertSame([], $empty->getValues());
        $this->assertSame(['A' => '1'], $loaded->getValues());
        $this->assertSame(['A' => '1', 'B' => '2'], $extended->getValues());
        $this->assertSame([], $reduced->getValues());
    }

    public function testStoreDoesNotCallPutenvByDefault(): void
    {
        (new Dotenv())->load('DOTENV_T_A=1')->store();

        $this->assertSame('1', $_ENV['DOTENV_T_A']);
        $this->assertFalse(getenv('DOTENV_T_A'));
    }

    public function testStoreRemovesUnsetValues(): void
    {
        $dotenv = new Dotenv([Dotenv::OPT_UNSAFE => true]);
        $dotenv->load('DOTENV_T_A=1')->store();
        $this->assertSame('1', getenv('DOTENV_T_A'));

        $dotenv->load('DOTENV_T_A=1')->withoutValue('DOTENV_T_A')->store();

        $this->assertArrayNotHasKey('DOTENV_T_A', $_ENV);
        $this->assertFalse(getenv('DOTENV_T_A'));
    }
}
