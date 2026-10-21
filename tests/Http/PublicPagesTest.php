<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\Context;
use Tests\Support\TestCase;

final class PublicPagesTest extends TestCase
{
    public function testPagesRenderInBothLanguages(): void
    {
        $pages = ['/', '/professions', '/professions/soc-analyst-junior', '/professions?category=5&q=analyste', '/simulations', '/features', '/resources', '/resources/star-method', '/privacy', '/login', '/register', '/password/forgot'];
        foreach (['fr' => 'Préparez vos entretiens.', 'en' => 'Prepare for interviews.'] as $lang => $title) {
            $c = Context::http();
            $c->get("/lang/$lang?redirect=/");
            $this->assertContains($title, $c->get('/')['body']);
            $this->assertContains('<html lang="' . $lang . '"', $c->get('/')['body']);
            foreach ($pages as $p) {
                $r = $c->get($p);
                $this->assertSame(200, $r['status'], $p);
                $this->assertTrue(preg_match('/\[[a-z_]+\.[a-z0-9_.]+\]/', $r['body']) === 0, "no missing translation on $p ($lang)");
            }
        }
    }

    public function testAcceptLanguageAndNotFound(): void
    {
        $c = Context::http();
        $r = $c->request('GET', '/', [], ['Accept-Language: en-GB,en;q=0.9']);
        $this->assertContains('Get ready for your next role.', $r['body']);
        $nf = $c->request('GET', '/professions/does-not-exist', [], ['Accept-Language: en']);
        $this->assertSame(404, $nf['status']);
        $this->assertContains('Page not found', $nf['body']);
        $this->assertSame(405, $c->request('PUT', '/professions')['status']);
    }

    public function testHomeHasNoInventedClaims(): void
    {
        $body = Context::http()->get('/')['body'];
        foreach (['témoignage', 'testimonial', 'certifié', '% de réussite', 'garanti'] as $claim) {
            $this->assertNotContains($claim, mb_strtolower($body));
        }
    }
}
