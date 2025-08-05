<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsertSbiOrganizationDocumentTypesData extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sbi_organization_document_types')->truncate();
        DB::table('sbi_organization_document_types')->insert(
            [
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Sole Proprietorship',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Partnership firm',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'y'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'HUF',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Private Limited Company',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public Limited Company',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Society',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Association of Persons',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Trust',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Liquidator',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Limited liability Partnership',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Liability Partnership',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Public sector banks',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Central/State Gov Department',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Exempted'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Section 8 Companies',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'N'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'Artificial Juridical Person',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'Y'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'International Organisation or Agency',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Not Categorized',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Others',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'OVD in respect of person authorized to transact',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Power of Atterney granted to manager',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Company Id Number',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Memorandum and Articles of Association',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Partnership Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Trust Deed',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'BoardResolution',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Activity Proof - 1',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Activity Proof - 2',
                    'document_type' => 'POI',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Company ID Number',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Registration Certificate',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ],
                [
                    'entity_type' => 'Foreign Portfolio Investors',
                    'document_name' => 'Others',
                    'document_type' => 'POA',
                    'active' => 'NA'
                ]
            ]
            
        );
    }
}
