<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPoliciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://diskartech.world', 'session.driver' => 'array', 'cache.default' => 'array']);
    }

    public function test_all_policies_are_public_readable_html_with_the_complete_published_content(): void
    {
        foreach (['privacy' => '/privacy-policy', 'terms' => '/terms-and-conditions', 'cookies' => '/cookie-policy'] as $key => $path) {
            $policy = config('legal.'.$key);
            $response = $this->get('https://diskartech.world'.$path)->assertOk()->assertViewIs('legal.policy')
                ->assertSee('<title>'.$policy['title'].' | DiskarTech</title>', false)
                ->assertSee($policy['introduction'])->assertSee('Updated '.$policy['updated'])
                ->assertSee('href="https://diskartech.world'.$path.'"', false)
                ->assertSee('mailto:diskartech.official@gmail.com', false);
            foreach ($policy['sections'] as $section) {
                $response->assertSee($section['title']);
                foreach ($section['paragraphs'] as $paragraph) $response->assertSee($paragraph);
                foreach ($section['bullets'] ?? [] as $bullet) $response->assertSee($bullet);
            }
            $this->assertSame(count($policy['sections']), substr_count($response->getContent(), '<section id="section-'));
        }
    }

    public function test_policy_text_is_escaped_in_html_and_page_metadata(): void
    {
        config(['legal.privacy.title' => '<script>private()</script>', 'legal.privacy.introduction' => '"><img src=x onerror=private()>',
            'legal.privacy.sections' => [['title' => '<b>Heading</b>', 'paragraphs' => ['<script>body()</script>'], 'bullets' => ['<img src=x onerror=bullet()>']]]]);
        $this->get('/privacy-policy')->assertOk()
            ->assertDontSee('<script>private()</script>', false)->assertSee('&lt;script&gt;private()&lt;/script&gt;', false)
            ->assertDontSee('<img src=x onerror=private()>', false)->assertDontSee('<script>body()</script>', false)
            ->assertDontSee('<img src=x onerror=bullet()>', false)->assertDontSee('<b>Heading</b>', false);
    }

    public function test_landing_footer_and_policy_pages_link_to_public_policy_urls(): void
    {
        $this->get('https://diskartech.world/')->assertOk()
            ->assertSee('href="https://diskartech.world/privacy-policy"', false)
            ->assertSee('href="https://diskartech.world/terms-and-conditions"', false)
            ->assertSee('href="https://diskartech.world/cookie-policy"', false);
        $this->get('https://diskartech.world/privacy-policy')->assertOk()
            ->assertSee('href="https://diskartech.world/terms-and-conditions"', false)
            ->assertSee('href="https://diskartech.world/cookie-policy"', false)
            ->assertSee('Settings &gt; Account &gt; Delete account', false)
            ->assertSee('https://privacy.gov.ph/data-subject-rights/', false);
    }

    public function test_policy_routes_are_get_only_and_do_not_shadow_mobile_account_endpoints(): void
    {
        $this->post('/privacy-policy')->assertStatus(405);
        $this->deleteJson('/api/user/account', [])->assertUnauthorized();
        $this->postJson('/api/user/deletion-code', [])->assertUnauthorized();
        $this->getJson('/api/user/account')->assertUnauthorized();
    }
}
