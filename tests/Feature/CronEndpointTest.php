<?php

namespace Tests\Feature;

use Tests\TestCase;

class CronEndpointTest extends TestCase
{
    public function test_it_is_disabled_when_no_token_is_configured(): void
    {
        config(['app.cron_token' => '']);

        $this->get('/cron/anything')->assertNotFound();
        $this->get('/cron/')->assertNotFound();
    }

    public function test_a_wrong_token_is_a_404(): void
    {
        config(['app.cron_token' => 'correct-token-123']);

        $this->get('/cron/wrong')->assertNotFound();
    }

    public function test_the_right_token_runs_the_scheduler(): void
    {
        config(['app.cron_token' => 'correct-token-123']);

        $this->get('/cron/correct-token-123')->assertOk()->assertSee('ok');
    }
}
