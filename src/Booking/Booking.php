<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Helper\DateHelper;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class Booking.
 */
class Booking
{
    /**
     * @var ResourceBookingResourceModel
     */
    private $objResource;

    /**
     * @var string
     */
    private $bookingUuid;

    /**
     * @var ModuleModel|null
     */
    private $moduleModel;

    /**
     * @var array
     */
    private $arrDateSelection = [];

    /**
     * @var int
     */
    private $bookingRepeatStopWeekTstamp = 0;

    /**
     * @var array
     */
    private $bookingArray = [];

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var FrontendUser
     */
    private $objUser;

    /**
     * Booking constructor.
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, string $bagName, Security $security)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;
    }

    /**
     * @throws \Exception
     */
    public function isBookingPossible(): bool
    {
        $arrBookings = $this->getBookingArray();

        if (!\is_array($arrBookings) || empty($arrBookings)) {
            return false;
        }

        foreach ($arrBookings as $arrBooking) {
            if (true === $arrBooking['invalidDate']) {
                return false;
            }

            if ($arrBooking['resourceIsAlreadyBooked'] && false === $arrBooking['resourceIsAlreadyBookedByLoggedInUser']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function getBookingArray(): array
    {
        if ($this->bookingArray) {
            return $this->bookingArray;
        }

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var ResourceBookingTimeSlotModel $resourceBookingTimeSlotModelAdapter */
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrBookings = [];

        $request = $this->requestStack->getCurrentRequest();
        $this->arrDateSelection = !empty($request->request->get('bookingDateSelection')) ? $request->request->get('bookingDateSelection') : [];

        if (!\is_array($this->arrDateSelection) || empty($this->arrDateSelection)) {
            return $arrBookings;
        }

        foreach ($this->arrDateSelection as $strTimeSlot) {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            // Defaults
            $arrData = [
                'id'                                    => null,
                'timeSlotId'                            => $arrTimeSlot[0],
                'startTime'                             => (int)$arrTimeSlot[1],
                'endTime'                               => (int)$arrTimeSlot[2],
                'date'                                  => '',
                'datim'                                 => '',
                'mondayTimestampSelectedWeek'           => (int)$arrTimeSlot[3],
                'pid'                                   => $inputAdapter->post('resourceId'),
                'bookingUuid'                           => '',
                'description'                           => $inputAdapter->post('description'),
                'member'                                => $this->objUser->id,
                'tstamp'                                => time(),
                'resourceIsAlreadyBooked'               => true,
                'resourceBlocked'                       => true,
                'invalidDate'                           => false,
                'resourceIsAlreadyBookedByLoggedInUser' => false,
                'newEntry'                              => false,
                'holder'                                => '',
            ];
            $arrBookings[] = $arrData;

            // Handle repetitions
            if ($arrTimeSlot[3] < $this->bookingRepeatStopWeekTstamp) {
                $doRepeat = true;

                while (true === $doRepeat) {
                    $arrRepeat = $arrData;
                    $arrRepeat['startTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['startTime']);
                    $arrRepeat['endTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['endTime']);
                    $arrRepeat['mondayTimestampSelectedWeek'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
                    $arrBookings[] = $arrRepeat;
                    // Stop repeating
                    if ($arrRepeat['mondayTimestampSelectedWeek'] >= $this->bookingRepeatStopWeekTstamp) {
                        $doRepeat = false;
                    }
                    $arrData = $arrRepeat;
                    unset($arrRepeat);
                }
            }
        }

        if (!empty($arrBookings)) {
            // Sort array by startTime
            usort(
                $arrBookings,
                static function ($a, $b) {
                    return $a['startTime'] <=> $b['startTime'];
                }
            );
        }

        foreach ($arrBookings as $i => $arrData) {
            // Set date
            $arrBookings[$i]['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']);
            $arrBookings[$i]['datim'] = sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $arrData['startTime']), $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['endTime']));

            // Set title
            $arrBookings[$i]['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getResource()->title,
                $GLOBALS['TL_LANG']['MSC']['bookingFor'],
                $this->objUser->firstname,
                $this->objUser->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrData['startTime']),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrData['endTime'])
            );

            // Set booking uuid
            $arrBookings[$i]['bookingUuid'] = $this->getBookingUuid();

            // Check if booking is possible
            if ($this->moduleModel->resourceBooking_addDateStop && $this->moduleModel->resourceBooking_dateStop + 24 * 3600 < $arrData['endTime']) {
                // Invalid time period
                $arrBookings[$i]['resourceBlocked'] = true;
                $arrBookings[$i]['invalidDate'] = true;
            } elseif (null !== $resourceBookingTimeSlotModelAdapter->findByPk($arrData['timeSlotId']) && !$this->isResourceBooked($this->getResource(), $arrData['startTime'], $arrData['endTime'])) {
                // All ok! Resource is bookable. -> override defaults
                $arrBookings[$i]['resourceBlocked'] = false;
                $arrBookings[$i]['resourceIsAlreadyBooked'] = false;
            } elseif (null !== ($objBooking = $resourceBookingModelAdapter->findOneByResourceIdStarttimeEndtimeAndMember($this->getResource(), $arrData['startTime'], $arrData['endTime'], $arrData['member']))) {
                // Resource has already been booked by the current/logged in user in a previous session
                $arrBookings[$i]['resourceBlocked'] = false;
                $arrBookings[$i]['resourceIsAlreadyBooked'] = true;
                $arrBookings[$i]['resourceIsAlreadyBookedByLoggedInUser'] = true;
                $arrBookings[$i]['id'] = $objBooking->id;
            } else {
                // This case normally should not happen
                $arrBookings[$i]['resourceBlocked'] = true;
                $arrBookings[$i]['resourceIsAlreadyBooked'] = true;
                $arrBookings[$i]['holder'] = '';

                $objRes = $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($this->getResource(), $arrData['startTime'], $arrData['endTime']);

                if (null !== $objRes) {
                    $arrBookings[$i]['holder'] = 'undefined';
                    $objMember = $memberModelAdapter->findByPk($objRes->member);

                    if (null !== $objMember) {
                        $arrBookings[$i]['holder'] = $stringUtilAdapter->substr($objMember->firstname, 1, '').'. '.$objMember->lastname;
                    }
                }
            }

            // Set "newEntry" to "true", if it is a new insert
            if (!$arrBookings[$i]['id']) {
                $arrBookings[$i]['newEntry'] = true;
            }
        }

        return $arrBookings;
    }

    /**
     * @throws \Exception
     */
    public function getResource(): ?ResourceBookingResourceModel
    {
        if (!$this->objResource) {
            /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
            $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

            $request = $this->requestStack->getCurrentRequest();

            $this->objResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId'));
        }

        return $this->objResource;
    }

    public function getBookingUuid(): string
    {
        if (!$this->bookingUuid) {
            /** @var StringUtil $stringUtilAdapter */
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

            /** @var Database $databaseAdapter */
            $databaseAdapter = $this->framework->getAdapter(Database::class);

            $this->bookingUuid = $stringUtilAdapter->binToUuid($databaseAdapter->getInstance()->getUuid());
        }

        return $this->bookingUuid;
    }

    public function isResourceBooked(ResourceBookingResourceModel $objResource, int $slotStartTime, int $slotEndTime): bool
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        if (null === $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime)) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function getBookingCollection(): Collection
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        $arrBookings = $this->getBookingArray();

        $bookingCollection = [];

        foreach ($arrBookings as $arrBooking) {
            if (true === $arrBooking['resourceIsAlreadyBookedByLoggedInUser'] && null !== $arrBooking['id']) {
                $objBooking = $resourceBookingModelAdapter->findByPk($arrBooking['id']);
            } else {
                $objBooking = new ResourceBookingModel();
            }

            if (null !== $objBooking) {
                foreach ($arrBooking as $k => $v) {
                    $objBooking->{$k} = $v;
                }
                $bookingCollection[] = $objBooking;
            }
        }

        return new Collection($bookingCollection, 'tl_resource_booking');
    }

    /**
     * @throws \Exception
     */
    public function initialize(): void
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            $this->objUser = $this->security->getUser();
        } else {
            throw new \Exception('No logged in user found.');
        }

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));

        if (null === $this->moduleModel) {
            throw new \Exception('Module model not found.');
        }

        // Get resource
        $request = $this->requestStack->getCurrentRequest();

        if (null === $this->getResource()) {
            throw new \Exception(sprintf('Resource with Id %s not found.', $request->request->get('resourceId')));
        }

        $this->bookingRepeatStopWeekTstamp = $request->request->get('bookingRepeatStopWeekTstamp', 0);

        if (!$this->bookingRepeatStopWeekTstamp > 0) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }
    }
}
