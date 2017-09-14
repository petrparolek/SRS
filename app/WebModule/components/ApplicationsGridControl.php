<?php

namespace App\WebModule\Components;

use App\Model\ACL\Role;
use App\Model\ACL\RoleRepository;
use App\Model\Enums\ApplicationState;
use App\Model\Enums\PaymentType;
use App\Model\Program\ProgramRepository;
use App\Model\Settings\SettingsRepository;
use App\Model\Structure\Subevent;
use App\Model\Structure\SubeventRepository;
use App\Model\User\Application;
use App\Model\User\ApplicationRepository;
use App\Model\User\UserRepository;
use App\Services\ApplicationService;
use App\Services\Authenticator;
use App\Services\MailService;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;


/**
 * Komponenta pro správu vlastních přihlášek.
 *
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class ApplicationsGridControl extends Control
{
    /** @var Translator */
    private $translator;

    /** @var ApplicationRepository */
    private $applicationRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var RoleRepository */
    private $roleRepository;

    /** @var SubeventRepository */
    private $subeventRepository;

    /** @var ApplicationService */
    private $applicationService;

    /** @var ProgramRepository */
    private $programRepository;

    /** @var MailService */
    private $mailService;

    /** @var SettingsRepository */
    private $settingsRepository;

    /** @var Authenticator */
    private $authenticator;

    /** @var User */
    private $user;


    /**
     * ApplicationsGridControl constructor.
     * @param Translator $translator
     * @param ApplicationRepository $applicationRepository
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param SubeventRepository $subeventRepository
     * @param ApplicationService $applicationService
     * @param ProgramRepository $programRepository
     * @param MailService $mailService
     */
    public function __construct(Translator $translator, ApplicationRepository $applicationRepository,
                                UserRepository $userRepository, RoleRepository $roleRepository,
                                SubeventRepository $subeventRepository, ApplicationService $applicationService,
                                ProgramRepository $programRepository, MailService $mailService,
                                SettingsRepository $settingsRepository, Authenticator $authenticator)
    {
        parent::__construct();

        $this->translator = $translator;
        $this->applicationRepository = $applicationRepository;
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->subeventRepository = $subeventRepository;
        $this->applicationService = $applicationService;
        $this->programRepository = $programRepository;
        $this->mailService = $mailService;
        $this->settingsRepository = $settingsRepository;
        $this->authenticator = $authenticator;
    }

    /**
     * Vykreslí komponentu.
     */
    public function render()
    {
        $this->template->render(__DIR__ . '/templates/applications_grid.latte');
    }

    /**
     * Vytvoří komponentu.
     * @param $name
     */
    public function createComponentApplicationsGrid($name)
    {
        $this->user = $this->userRepository->findById($this->getPresenter()->getUser()->getId());

        $grid = new DataGrid($this, $name);
        $grid->setTranslator($this->translator);
        $grid->setDataSource($this->applicationRepository->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->where('u.id = ' . $this->user->getId())
        );
        $grid->setPagination(FALSE);

        $grid->addColumnDateTime('applicationDate', 'web.profile.applications_application_date')
            ->setFormat('j. n. Y H:i');

        $grid->addColumnText('roles', 'web.profile.applications_roles')
            ->setRenderer(function ($row) {
                if (!$row->isFirst())
                    return "";

                $roles = [];
                foreach ($row->getUser()->getRoles() as $role) {
                    $roles[] = $role->getName();
                }
                return implode(", ", $roles);
            });

        if ($this->subeventRepository->countExplicitSubevents() > 0) {
            $grid->addColumnText('subevents', 'web.profile.applications_subevents')
                ->setRenderer(function ($row) {
                    $subevents = [];
                    foreach ($row->getSubevents() as $subevent) {
                        $subevents[] = $subevent->getName();
                    }
                    return implode(", ", $subevents);
                });
        }

        $grid->addColumnNumber('fee', 'web.profile.applications_fee');

        $grid->addColumnText('variable_symbol', 'web.profile.applications_variable_symbol');

        $grid->addColumnDateTime('maturityDate', 'web.profile.applications_maturity_date')
            ->setFormat('j. n. Y');

        $grid->addColumnText('state', 'web.profile.applications_state')
            ->setRenderer(function ($row) {
                $state = $this->translator->translate('common.application_state.' . $row->getState());

                if ($row->getState() == ApplicationState::PAID && $row->getPaymentDate() !== NULL)
                    $state .= ' (' . $row->getPaymentDate()->format('j. n. Y') . ')';

                return $state;
            });

        if ($this->applicationService->isAllowedAddSubevents($this->user)) {
            $grid->addInlineAdd()->onControlAdd[] = function ($container) {
                $subeventsSelect = $container->addMultiSelect('subevents', '', $this->subeventRepository->getNonRegisteredExplicitOptionsWithCapacity($this->user))
                    ->setAttribute('class', 'datagrid-multiselect')
                    ->addRule(Form::FILLED, 'web.profile.applications_subevents_empty');
            };
            $grid->getInlineAdd()->setIcon(NULL);
            $grid->getInlineAdd()->setText($this->translator->translate('web.profile.applications_add_subevents'));
            $grid->getInlineAdd()->onSubmit[] = [$this, 'add'];
        }

        if ($this->applicationService->isAllowedEditFirstApplication($this->user)) {
            $grid->addInlineEdit()->onControlAdd[] = function ($container) {
                $rolesSelect = $container->addMultiSelect('roles', '', $this->roleRepository->getRegisterableNowOrUsersOptionsWithCapacity($this->user))
                    ->setAttribute('class', 'datagrid-multiselect')
                    ->addRule(Form::FILLED, 'web.profile.applications_roles_empty');

                if ($this->subeventRepository->countExplicitSubevents() > 0) {
                    $subeventsSelect = $container->addMultiSelect('subevents', '', $this->subeventRepository->getExplicitOptionsWithCapacity())
                        ->setAttribute('class', 'datagrid-multiselect')
                        ->addRule(Form::FILLED, 'web.profile.applications_subevents_empty');
                }
            };
            $grid->getInlineEdit()->setIcon(NULL);
            $grid->getInlineEdit()->setText($this->translator->translate('web.profile.applications_edit'));
            $grid->getInlineEdit()->onSetDefaults[] = function ($container, $item) {
                $container->setDefaults([
                    'roles' => $this->roleRepository->findRolesIds($item->getUser()->getRoles()),
                    'subevents' => $this->subeventRepository->findSubeventsIds($item->getSubevents())
                ]);
            };
            $grid->getInlineEdit()->onSubmit[] = [$this, 'edit'];
        }

        $grid->addAction('generatePaymentProofBank', 'web.profile.applications_download_payment_proof');
        $grid->allowRowsAction('generatePaymentProofBank', function ($item) {
            return $item->getPaymentMethod() == PaymentType::BANK;
        });

        $grid->setColumnsSummary(['fee']);
    }

    /**
     * Zpracuje přidání podakcí.
     * @param $values
     */
    public function add($values)
    {
        $selectedSubevents = $this->subeventRepository->findSubeventsByIds($values['subevents']);
        $selectedAndUsersSubevents = $this->user->getSubevents();
        foreach ($selectedSubevents as $subevent)
            $selectedAndUsersSubevents->add($subevent);

        //kontrola podakci
        if (!$this->checkSubeventsCapacities($selectedSubevents)) {
            $this->getPresenter()->flashMessage('web.profile.applications_subevents_capacity_occupied', 'danger');
            $this->redirect('this');
        }

        foreach ($this->subeventRepository->findAllExplicitOrderedByName() as $subevent) {
            $incompatibleSubevents = $subevent->getIncompatibleSubevents();
            if (count($incompatibleSubevents) > 0 && !$this->checkSubeventsIncompatible($selectedAndUsersSubevents, $subevent)) {
                $messageThis = $subevent->getName();

                $first = TRUE;
                $messageOthers = "";
                foreach ($incompatibleSubevents as $incompatibleSubevent) {
                    if ($first)
                        $messageOthers .= $incompatibleSubevent->getName();
                    else
                        $messageOthers .= ", " . $incompatibleSubevent->getName();

                    $first = FALSE;
                }

                $message = $this->translator->translate('web.profile.applications_incompatible_subevents_selected', NULL,
                    ['subevent' => $messageThis, 'incompatibleSubevents' => $messageOthers]
                );
                $this->getPresenter()->flashMessage($message, 'danger');
                $this->redirect('this');
            }

            $requiredSubevents = $subevent->getRequiredSubeventsTransitive();
            if (count($requiredSubevents) > 0 && !$this->checkSubeventsRequired($selectedAndUsersSubevents, $subevent)) {
                $messageThis = $subevent->getName();

                $first = TRUE;
                $messageOthers = "";
                foreach ($requiredSubevents as $requiredSubevent) {
                    if ($first)
                        $messageOthers .= $requiredSubevent->getName();
                    else
                        $messageOthers .= ", " . $requiredSubevent->getName();
                    $first = FALSE;
                }

                $message = $this->translator->translate('web.profile.applications_required_subevents_not_selected', NULL,
                    ['subevent' => $messageThis, 'requiredSubevents' => $messageOthers]
                );
                $this->getPresenter()->flashMessage($message, 'danger');
                $this->redirect('this');
            }
        }

        //zpracovani zmen
        $application = new Application();
        $fee = $this->applicationService->countFee($this->user->getRoles(), $selectedSubevents, FALSE);
        $application->setUser($this->user);
        $application->setSubevents($selectedSubevents);
        $application->setApplicationDate(new \DateTime());
        $application->setApplicationOrder($this->applicationRepository->findLastApplicationOrder() + 1);
        $application->setMaturityDate($this->applicationService->countMaturityDate());
        $application->setVariableSymbol($this->applicationService->generateVariableSymbol($this->user));
        $application->setFee($fee);
        $application->setState($fee == 0 ? ApplicationState::PAID : ApplicationState::WAITING_FOR_PAYMENT);
        $application->setFirst(FALSE);
        $this->applicationRepository->save($application);

        $this->programRepository->updateUserPrograms($this->user);
        $this->userRepository->save($this->user);

//        $rolesNames = "";
//        $first = TRUE;
//        foreach ($this->user->getRoles() as $role) {
//            if ($first) {
//                $rolesNames = $role->getName();
//                $first = FALSE;
//            }
//            else {
//                $rolesNames .= ', ' . $role->getName();
//            }
//        }

        //TODO mail vcetne podakci
//        $this->mailService->sendMailFromTemplate(new ArrayCollection(), new ArrayCollection([$this->user]), '', Template::ROLE_CHANGED, [
//            TemplateVariable::SEMINAR_NAME => $this->settingsRepository->getValue(Settings::SEMINAR_NAME),
//            TemplateVariable::USERS_ROLES => $rolesNames
//        ]);

        $this->getPresenter()->flashMessage('web.profile.applications_add_subevents_successful', 'success');
        $this->redirect('this');
    }

    /**
     * Zpracuje úpravu přihlášky.
     * @param $id
     * @param $values
     */
    public function edit($id, $values)
    {
        $selectedRoles = $this->roleRepository->findRolesByIds($values['roles']);

        //kontrola roli
        if (!$this->checkRolesCapacities($selectedRoles)) {
            $this->getPresenter()->flashMessage('web.profile.applications_roles_capacity_occupied', 'danger');
            $this->redirect('this');
        }

        if (!$this->checkRolesRegisterable($selectedRoles)) {
            $this->getPresenter()->flashMessage('web.profile.applications_role_is_not_registerable', 'danger');
            $this->redirect('this');
        }

        foreach ($this->roleRepository->findAllRegisterableNowOrUsersOrderedByName($this->user) as $role) {
            $incompatibleRoles = $role->getIncompatibleRoles();
            if (count($incompatibleRoles) > 0 && !$this->checkRolesIncompatible($selectedRoles, $role)) {
                $messageThis = $role->getName();

                $first = TRUE;
                $messageOthers = "";
                foreach ($incompatibleRoles as $incompatibleRole) {
                    if ($incompatibleRole->isRegisterableNow()) {
                        if ($first)
                            $messageOthers .= $incompatibleRole->getName();
                        else
                            $messageOthers .= ", " . $incompatibleRole->getName();
                    }
                    $first = FALSE;
                }

                $message = $this->translator->translate('web.profile.applications_incompatible_roles_selected', NULL,
                    ['role' => $messageThis, 'incompatibleRoles' => $messageOthers]
                );
                $this->getPresenter()->flashMessage($message, 'danger');
                $this->redirect('this');
            }

            $requiredRoles = $role->getRequiredRolesTransitive();
            if (count($requiredRoles) > 0 && !$this->checkRolesRequired($selectedRoles, $role)) {
                $messageThis = $role->getName();

                $first = TRUE;
                $messageOthers = "";
                foreach ($requiredRoles as $requiredRole) {
                    if ($first)
                        $messageOthers .= $requiredRole->getName();
                    else
                        $messageOthers .= ", " . $requiredRole->getName();
                    $first = FALSE;
                }

                $message = $this->translator->translate('web.profile.applications_required_roles_not_selected', NULL,
                    ['role' => $messageThis, 'requiredRoles' => $messageOthers]
                );
                $this->getPresenter()->flashMessage($message, 'danger');
                $this->redirect('this');
            }
        }


        if ($this->subeventRepository->countExplicitSubevents() > 0) {
            $selectedSubevents = $this->subeventRepository->findSubeventsByIds($values['subevents']);

            //kontrola podakci
            if (!$this->checkSubeventsCapacities($selectedSubevents)) {
                $this->getPresenter()->flashMessage('web.profile.applications_subevents_capacity_occupied', 'danger');
                $this->redirect('this');
            }

            foreach ($this->subeventRepository->findAllExplicitOrderedByName() as $subevent) {
                $incompatibleSubevents = $subevent->getIncompatibleSubevents();
                if (count($incompatibleSubevents) > 0 && !$this->checkSubeventsIncompatible($selectedSubevents, $subevent)) {
                    $messageThis = $subevent->getName();

                    $first = TRUE;
                    $messageOthers = "";
                    foreach ($incompatibleSubevents as $incompatibleSubevent) {
                        if ($first)
                            $messageOthers .= $incompatibleSubevent->getName();
                        else
                            $messageOthers .= ", " . $incompatibleSubevent->getName();

                        $first = FALSE;
                    }

                    $message = $this->translator->translate('web.profile.applications_incompatible_subevents_selected', NULL,
                        ['subevent' => $messageThis, 'incompatibleSubevents' => $messageOthers]
                    );
                    $this->getPresenter()->flashMessage($message, 'danger');
                    $this->redirect('this');
                }

                $requiredSubevents = $subevent->getRequiredSubeventsTransitive();
                if (count($requiredSubevents) > 0 && !$this->checkSubeventsRequired($selectedSubevents, $subevent)) {
                    $messageThis = $subevent->getName();

                    $first = TRUE;
                    $messageOthers = "";
                    foreach ($requiredSubevents as $requiredSubevent) {
                        if ($first)
                            $messageOthers .= $requiredSubevent->getName();
                        else
                            $messageOthers .= ", " . $requiredSubevent->getName();
                        $first = FALSE;
                    }

                    $message = $this->translator->translate('web.profile.applications_required_subevents_not_selected', NULL,
                        ['subevent' => $messageThis, 'requiredSubevents' => $messageOthers]
                    );
                    $this->getPresenter()->flashMessage($message, 'danger');
                    $this->redirect('this');
                }
            }


            //pokud si uživatel přidá roli, která vyžaduje schválení, stane se neschválený
            $approved = TRUE;
            if ($approved) {
                foreach ($selectedRoles as $role) {
                    if (!$role->isApprovedAfterRegistration() && !$this->user->getRoles()->contains($role)) {
                        $approved = FALSE;
                        break;
                    }
                }
            }
        }


        //zpracovani zmen
        $this->user->setRoles($selectedRoles);
        $this->user->setApproved($approved);
        $this->userRepository->save($this->user);

        $fee = $this->applicationService->countFee($selectedRoles, $selectedSubevents);
        $application = $this->applicationRepository->findById($id);
        if ($this->subeventRepository->countExplicitSubevents() > 0)
            $application->setSubevents($selectedSubevents);
        $application->setFee($fee);
        $application->setState($fee == 0 ? ApplicationState::PAID : ApplicationState::WAITING_FOR_PAYMENT);
        $this->applicationRepository->save($application);

        $this->programRepository->updateUserPrograms($this->user);
        $this->userRepository->save($this->user);

//        $rolesNames = "";
//        $first = TRUE;
//        foreach ($this->user->getRoles() as $role) {
//            if ($first) {
//                $rolesNames = $role->getName();
//                $first = FALSE;
//            }
//            else {
//                $rolesNames .= ', ' . $role->getName();
//            }
//        }

        //TODO mail vcetne podakci
//        $this->mailService->sendMailFromTemplate(new ArrayCollection(), new ArrayCollection([$this->user]), '', Template::ROLE_CHANGED, [
//            TemplateVariable::SEMINAR_NAME => $this->settingsRepository->getValue(Settings::SEMINAR_NAME),
//            TemplateVariable::USERS_ROLES => $rolesNames
//        ]);

        $this->authenticator->updateRoles($this->getPresenter()->getUser());

        $this->getPresenter()->flashMessage('web.profile.applications_edit_successful', 'success');
        $this->redirect('this');
    }

    /**
     * Vygeneruje potvrzení o přijetí platby.
     */
    public function handleGeneratePaymentProofBank($id)
    {
        //TODO generovani potvrzeni o zaplaceni
//        if (!$this->user->getIncomeProofPrintedDate()) {
//            $this->user->setIncomeProofPrintedDate(new \DateTime());
//            $this->userRepository->save($user);
//        }
//        $this->pdfExportService->generatePaymentProof($user, "potvrzeni-o-prijeti-platby.pdf");
    }

    /**
     * Ověří kapacitu rolí.
     * @param $selectedRoles
     * @return bool
     */
    public function checkRolesCapacities($selectedRoles)
    {
        foreach ($selectedRoles as $role) {
            if ($role->hasLimitedCapacity()) {
                if ($this->roleRepository->countUnoccupiedInRole($role) < 1 && !$this->user->isInRole($role))
                    return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Ověří kompatibilitu rolí.
     * @param $selectedRoles
     * @param $testRole
     * @return bool
     */
    public function checkRolesIncompatible($selectedRoles, Role $testRole)
    {
        if (!$selectedRoles->contains($testRole))
            return TRUE;

        foreach ($testRole->getIncompatibleRoles() as $incompatibleRole) {
            if ($selectedRoles->contains($incompatibleRole))
                return FALSE;
        }

        return TRUE;
    }

    /**
     * Ověří výběr vyžadovaných rolí.
     * @param $selectedRoles
     * @param $testRole
     * @return bool
     */
    public function checkRolesRequired($selectedRoles, Role $testRole)
    {
        if (!$selectedRoles->contains($testRole))
            return TRUE;

        foreach ($testRole->getRequiredRolesTransitive() as $requiredRole) {
            if (!$selectedRoles->contains($requiredRole))
                return FALSE;
        }

        return TRUE;
    }

    /**
     * Ověří registrovatelnost rolí.
     * @param $selectedRoles
     * @return bool
     */
    public function checkRolesRegisterable($selectedRoles)
    {
        foreach ($selectedRoles as $role) {
            if (!$role->isRegisterableNow() && !$this->user->isInRole($role))
                return FALSE;
        }
        return TRUE;
    }

    /**
     * Ověří kapacitu podakcí.
     * @param $selectedSubevents
     * @return bool
     */
    public function checkSubeventsCapacities($selectedSubevents)
    {
        foreach ($selectedSubevents as $subevent) {
            if ($subevent->hasLimitedCapacity()) {
                if ($this->subeventRepository->countUnoccupiedInSubevent($subevent) < 1 && !$this->user->hasSubevent($subevent))
                    return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Ověří kompatibilitu podakcí.
     * @param $selectedSubevents
     * @param Subevent $testSubevent
     * @return bool
     */
    public function checkSubeventsIncompatible($selectedSubevents, Subevent $testSubevent)
    {
        if (!$selectedSubevents->contains($testSubevent))
            return TRUE;

        foreach ($testSubevent->getIncompatibleSubevents() as $incompatibleSubevent) {
            if ($selectedSubevents->contains($incompatibleSubevent))
                return FALSE;
        }

        return TRUE;
    }

    /**
     * Ověří výběr vyžadovaných podakcí.
     * @param $selectedSubevents
     * @param Subevent $testSubevent
     * @return bool
     */
    public function checkSubeventsRequired($selectedSubevents, Subevent $testSubevent)
    {
        if (!$selectedSubevents->contains($testSubevent))
            return TRUE;

        foreach ($testSubevent->getRequiredSubeventsTransitive() as $requiredSubevent) {
            if (!$selectedSubevents->contains($requiredSubevent))
                return FALSE;
        }

        return TRUE;
    }
}
