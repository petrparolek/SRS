<?php

namespace App\Commands\Fixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Nette\Security\Passwords;
use Nette\Utils\Neon;
use App\Model\Settings\Settings;

class SettingsFixture extends AbstractFixture
{
    /**
     * @var \Kdyby\Translation\Translator
     */
    protected $translator;

    /**
     * @var \App\Services\ConfigFacade
     */
    protected $configFacade;

    /**
     * SettingsFixture constructor.
     * @param \Kdyby\Translation\Translator $translator
     * @param \App\Services\ConfigFacade $configFacade
     */
    public function __construct(\Kdyby\Translation\Translator $translator, \App\Services\ConfigFacade $configFacade)
    {
        $this->translator = $translator;
        $this->configFacade = $configFacade;
    }

    public function load(ObjectManager $manager)
    {
        $config = $this->configFacade->getConfig();
        $customBooleanCount = $config['parameters']['customInputs']['booleanCount'];
        $customTextCount = $config['parameters']['customInputs']['textCount'];

        $today = new \DateTime('now');
        $tommorow = new \DateTime('now');
        $tommorow->modify('+1 day');
        $yesterday = new \DateTime('now');
        $yesterday->modify('-1 day');

        $settings = array();
        $settings[] = new Settings('admin_created', '0');

        $settings[] = new Settings('seminar_name', $this->translator->translate('common.settings.default.seminar_name'));
        $settings[] = new Settings('seminar_email', $this->translator->translate('common.settings.default.seminar_email'));
        $settings[] = new Settings('seminar_from_date', $today->format('Y-m-d'));
        $settings[] = new Settings('seminar_to_date', $tommorow->format('Y-m-d'));

        $settings[] = new Settings('basic_block_duration', '60');
        $settings[] = new Settings('is_allowed_add_block', '1');
        $settings[] = new Settings('is_allowed_modify_schedule', '1');
        $settings[] = new Settings('is_allowed_log_in_programs', '0');
        $settings[] = new Settings('is_allowed_log_in_programs_before_payment', '0');

        $settings[] = new Settings('skautis_action_id', '');
        $settings[] = new Settings('skautis_action_name', '');

        $settings[] = new Settings('logo', '/img/logo.png');
        $settings[] = new Settings('footer', $this->translator->translate('common.settings.default.footer', ['year' => $today->format('Y')]));

        $settings[] = new Settings('company', $this->translator->translate('common.settings.default.company'));
        $settings[] = new Settings('ico', $this->translator->translate('common.settings.default.ico'));
        $settings[] = new Settings('accountant', $this->translator->translate('common.settings.default.accountant'));
        $settings[] = new Settings('print_location', $this->translator->translate('common.settings.default.print_location'));
        $settings[] = new Settings('account_number', $this->translator->translate('common.settings.default.account_number'));
        $settings[] = new Settings('variable_symbol_code', '00');

        $settings[] = new Settings('log_in_programs_from', $yesterday->format('Y-m-d H:i:s'));
        $settings[] = new Settings('log_in_programs_to', $today->format('Y-m-d H:i:s'));
        $settings[] = new Settings('cancel_registration_to', $today->format('Y-m-d'));

        $settings[] = new Settings('display_users_roles', '1');
        $settings[] = new Settings('redirect_after_login', '/');

        for ($i = 1; $i <= $customBooleanCount; $i++) {
            $settings[] = new Settings('custom_boolean_' . $i, '');
        }

        for ($i = 1; $i <= $customTextCount; $i++) {
            $settings[] = new Settings('custom_text_' . $i, '');
        }

        foreach ($settings as $setting) {
            $manager->persist($setting);
        }

        $manager->flush();
    }
}