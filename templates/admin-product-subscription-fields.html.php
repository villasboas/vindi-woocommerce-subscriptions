<?php if (!defined( 'ABSPATH')) exit; ?>
<style type="text/css">
    ._subscription_period_interval_field,
    ._subscription_period_field,
    ._subscription_length_field,
    ._subscription_sign_up_fee_field,
    ._subscription_trial_length_field,
    ._subscription_trial_period_field {
        display: none !important;
    }
</style>

<div class="options_group vindi-subscription_pricing show_if_subscription">

<?php
    woocommerce_wp_select(array(
        'id'          => 'vindi_subscription_plan',
        'label'       => __('Plano da Vindi', VINDI_IDENTIFIER),
        'options'     => $plans,
        'description' => __('Selecione o plano da Vindi que deseja relacionar a esse produto', VINDI_IDENTIFIER),
        'desc_tip'    => true,
        'value'       => $selected_plan,
    ));

    woocommerce_wp_text_input(array(
        'id'                => 'vindi_subscription_plan_period',
        'label'             => '',
        'type'              => 'hidden'
    ));
?>

</div>
<div class="show_if_subscription clear"></div>