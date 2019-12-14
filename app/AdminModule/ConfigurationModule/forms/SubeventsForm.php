<?php

declare(strict_types=1);

namespace App\AdminModule\ConfigurationModule\Forms;

use App\AdminModule\Forms\BaseForm;
use App\Model\Settings\Settings;
use App\Model\Settings\SettingsException;
use App\Services\SettingsService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Throwable;

/**
 * Formulář pro nastavení podakcí.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class SubeventsForm
{
    use Nette\SmartObject;

    /** @var BaseForm */
    private $baseFormFactory;

    /** @var SettingsService */
    private $settingsService;


    public function __construct(BaseForm $baseForm, SettingsService $settingsService)
    {
        $this->baseFormFactory = $baseForm;
        $this->settingsService = $settingsService;
    }

    /**
     * Vytvoří formulář.
     * @throws SettingsException
     * @throws Throwable
     */
    public function create() : Form
    {
        $form = $this->baseFormFactory->create();

        $form->addCheckbox('isAllowedAddSubeventsAfterPayment', 'admin.configuration.is_allowed_add_subevents_after_payment');

        $form->addSubmit('submit', 'admin.common.save');

        $form->setDefaults([
            'isAllowedAddSubeventsAfterPayment' => $this->settingsService->getValue(Settings::IS_ALLOWED_ADD_SUBEVENTS_AFTER_PAYMENT),
        ]);

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    /**
     * Zpracuje formulář.
     * @throws SettingsException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Throwable
     */
    public function processForm(Form $form, stdClass $values) : void
    {
        $this->settingsService->setBoolValue(Settings::IS_ALLOWED_ADD_SUBEVENTS_AFTER_PAYMENT, $values->isAllowedAddSubeventsAfterPayment);
    }
}
