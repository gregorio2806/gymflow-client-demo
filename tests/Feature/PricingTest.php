<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_config_matches_the_agreed_pricing()
    {
        $plans = config('plans.plans');

        $this->assertSame(999, $plans['starter']['monthly_price']);
        $this->assertSame(1999, $plans['pro']['monthly_price']);
        $this->assertSame(2999, $plans['multi_branch']['monthly_price']);
        $this->assertSame(999, $plans['multi_branch']['extra_branch_monthly_price']);
        $this->assertSame(10, config('plans.yearly_months_charged'));

        $this->assertSame(['branches' => 1, 'staff_accounts' => 2, 'active_members' => 150], $plans['starter']['limits']);
        $this->assertSame(['branches' => 1, 'staff_accounts' => 5, 'active_members' => 500], $plans['pro']['limits']);
        $this->assertSame(2, $plans['multi_branch']['limits']['branches']);
        $this->assertSame(5, $plans['multi_branch']['limits']['staff_accounts_per_branch']);
        $this->assertNull($plans['multi_branch']['limits']['active_members']);
    }

    public function test_landing_page_shows_monthly_and_yearly_prices_for_every_plan()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['Starter', '₱999', 'Pro', '₱1,999', 'Multi-Branch', '₱2,999'])
            // Yearly = pay 10 months, get 12
            ->assertSee('₱9,990')
            ->assertSee('₱19,990')
            ->assertSee('₱29,990')
            ->assertSee('Save ₱1,998')
            // Extra branches on Multi-Branch
            ->assertSee('+₱999/month per extra branch')
            ->assertSee('+₱9,990/year per extra branch')
            ->assertSee('Most popular');
    }

    public function test_each_card_lists_every_feature_and_grays_out_missing_ones()
    {
        $html = $this->get(route('home'))->getContent();

        // Split the pricing section into its three cards.
        $section = substr($html, strpos($html, 'id="pricing"'));
        $section = substr($section, 0, strpos($section, 'Compare all features'));
        $cardHtml = array_slice(preg_split('/<h3 class="[^"]*">/', $section), 1); // one chunk per plan card
        $this->assertCount(3, $cardHtml);
        [$starter, $pro, $multi] = $cardHtml;

        $featureCount = collect(config('plans.card_features'))->flatten(1)->count();
        $categories = array_keys(config('plans.card_features'));
        foreach ([$starter, $pro, $multi] as $card) {
            $this->assertSame($featureCount, substr_count($card, '<li class="flex items-start gap-3'), 'Every card lists every feature.');

            // Features are grouped under the same category headings, in the same order, on every card.
            preg_match_all('/<h4[^>]*>(.*?)<\/h4>/', $card, $headings);
            $this->assertSame($categories, array_map('html_entity_decode', $headings[1]));
        }

        // Pro lacks the branch features; Multi-Branch lacks nothing.
        $this->assertStringContainsString('Not included: </span>Members can use any branch', $pro);
        $this->assertSame(0, substr_count($multi, 'Not included:'));
        $this->assertSame(3, substr_count($starter, 'Not included:'));
        $this->assertSame(3, substr_count($pro, 'Not included:'));

        // Plan-specific wording replaces the generic label.
        $this->assertStringContainsString('Reports: up to 31 days', $starter);
        $this->assertStringContainsString('Gym-wide / branch reports + CSV export', $multi);
        foreach (['Online payments', 'Email expiry reminders', 'Excel/PDF', 'Staff activity log', 'Custom gym page per branch', 'export your gym&#039;s data'] as $unimplemented) {
            $this->assertStringNotContainsString($unimplemented, $html);
        }
        $this->assertStringNotContainsString('Messenger / email', $html);
        $this->assertStringNotContainsString('Priority support', $html);
        $this->assertStringNotContainsString('monthly check-in call', $html);
        $this->assertStringNotContainsString('>Support</', $html);
    }

    public function test_billing_switch_works_without_javascript()
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertStringContainsString('id="billing-monthly" value="monthly" class="sr-only" checked', $html);
        $this->assertStringContainsString('id="billing-yearly"', $html);
        $this->assertStringContainsString('group-has-[#billing-yearly:checked]/pricing:hidden', $html);
        $this->assertStringContainsString('group-has-[#billing-yearly:checked]/pricing:block', $html);
    }

    public function test_comparison_table_lists_every_row()
    {
        $response = $this->get(route('home'))->assertSee('Compare all features');

        foreach (config('plans.comparison') as [$feature]) {
            $response->assertSee($feature);
        }
    }

    public function test_plan_buttons_open_the_request_form_with_that_plan()
    {
        $response = $this->get(route('home'))
            ->assertSee('Request Starter')
            ->assertSee('Request Pro')
            ->assertSee('Request Multi-Branch');

        foreach (['starter', 'pro', 'multi_branch'] as $plan) {
            $response->assertSee(route('access-requests.create', ['plan' => $plan]), false);
        }
    }
}
