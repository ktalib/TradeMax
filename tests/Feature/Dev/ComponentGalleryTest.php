<?php

declare(strict_types=1);

it('renders every UI component in the gallery', function (): void {
    $this->get('/dev/ui')
        ->assertOk()
        ->assertSee('Component gallery')
        ->assertSee(['Buttons', 'Badges', 'Stat cards, amounts and PnL', 'Table with asset icons', 'Forms', 'Tabs, stepper, progress, countdown', 'Modal, toast, tooltip'])
        ->assertSee('class="shell"', false)
        ->assertSee('aria-disabled="true"', false);
});

it('formats amounts and PnL through Decimal', function (): void {
    $this->get('/dev/ui')
        ->assertSee('1,234,567.89')
        ->assertSee('0.4215')
        ->assertSee('+2.31%')
        ->assertSee('−3.44%')
        ->assertSee('13,980.55');
});

it('emphasises both ends of an address', function (): void {
    $this->get('/dev/ui')->assertSee('<span class="font-medium text-fg">TQx7mW</span>', false);
});

it('switches to right-to-left for Arabic', function (): void {
    $this->withHeader('Accept-Language', 'ar')->get('/dev/ui')->assertSee('dir="rtl"', false);
});
