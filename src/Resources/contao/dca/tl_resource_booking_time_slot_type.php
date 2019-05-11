<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_resource_booking_time_slot_type'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'    => 'Table',
        'ctable'           => array('tl_resource_booking_time_slot'),
        'switchToEdit'     => true,
        'enableVersioning' => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id'    => 'primary',
                'title' => 'unique'
            )
        )
    ),

    // List
    'list'     => array
    (
        'sorting' => array
        (
            'mode'                    => 1,
            'fields'                  => array('title'),
            'flag'                    => 1,
            'panelLayout'             => 'filter;search,limit'
        ),
        'label' => array
        (
            'fields'                  => array('title'),
            'format'                  => '%s'
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array
        (
            'edit'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['edit'],
                'href'  => 'table=tl_resource_booking_time_slot',
                'icon'  => 'edit.svg'
            ),
            'editheader' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['editheader'],
                'href'            => 'table=tl_resource_booking_time_slot_type&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => array('tl_resource_booking_time_slot_type', 'editHeader')
            ),
            'copy'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['copy'],
                'href'  => 'act=paste&amp;mode=copy',
                'icon'  => 'copy.svg'
            ),
            'cut'        => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['cut'],
                'href'       => 'act=paste&amp;mode=cut',
                'icon'       => 'cut.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'delete'     => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{title_legend},published,title,description'
    ),

    // Fields
    'fields'   => array
    (
        'id'          => array
        (
            'label'  => array('ID'),
            'search' => true,
            'sql'    => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'      => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'title'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['title'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'published'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['published'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'flag'      => 2,
            'inputType' => 'checkbox',
            'eval'      => array('doNotCopy' => true, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'description' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => array(),
            'sql'       => "mediumtext NULL"
        )
    )
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_booking_time_slot_type extends Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
    }


    /**
     * Return the edit header button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label) . '</a> ';
    }
}