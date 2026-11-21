<?php

namespace Tests\Unit;

use App\Http\Middleware\RedirectIndexPhpPaths;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * /index.php/<page> answered with the page itself (a 200), so every page also existed under an /index.php/ prefix and
 * Search Console listed such addresses. They now redirect permanently to the clean address.
 */
class RedirectIndexPhpPathsTest extends TestCase
{
    private function through(string $method, string $uri): Response
    {
        $request = Request::create($uri, $method);

        return (new RedirectIndexPhpPaths)->handle($request, fn () => new Response('passed'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function redirected(): array
    {
        return [
            'page under the prefix' => ['/index.php/fr/parts/A3094210211', '/fr/parts/A3094210211'],
            'query string kept' => ['/index.php/en/blog?page=2', '/en/blog?page=2'],
            'prefix in capitals' => ['/INDEX.PHP/en/about', '/en/about'],
            'the script on its own' => ['/index.php', '/'],
            'trailing slash only' => ['/index.php/', '/'],
        ];
    }

    #[Test]
    #[DataProvider('redirected')]
    public function it_redirects_permanently_to_the_clean_address(string $uri, string $expected): void
    {
        $response = $this->through('GET', $uri);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(url($expected), $response->headers->get('Location'));
    }

    #[Test]
    public function head_requests_are_redirected_too(): void
    {
        $this->assertSame(301, $this->through('HEAD', '/index.php/en/about')->getStatusCode());
    }

    #[Test]
    #[DataProvider('untouched')]
    public function other_addresses_pass_through(string $method, string $uri): void
    {
        $response = $this->through($method, $uri);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passed', $response->getContent());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function untouched(): array
    {
        return [
            'a normal page' => ['GET', '/en/about'],
            'the home page' => ['GET', '/'],
            'a page whose name contains index.php later' => ['GET', '/en/blog/what-is-index.php-and-why'],
            'a form posting to the prefix keeps working' => ['POST', '/index.php/en/contact'],
        ];
    }
}
