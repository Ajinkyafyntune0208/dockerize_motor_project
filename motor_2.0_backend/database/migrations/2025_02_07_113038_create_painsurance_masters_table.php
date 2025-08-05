<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePAinsuranceMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('pa_insurance_masters')) {
            // table exists do nothing just insert data
        } else {
            Schema::create('pa_insurance_masters', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('value')->nullable();
                $table->string('partyid')->nullable();
                $table->index('partyid');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        DB::table('pa_insurance_masters')->truncate();
        DB::table('pa_insurance_masters')->insert([
            ['id' => 1, 'name' => 'National Insurance Co. Ltd.', 'value' => 'National Insurance Co. Ltd.', 'partyid' => 'PINC0001', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'The New India Assurance Co. Ltd.', 'value' => 'The New India Assurance Co. Ltd.', 'partyid' => 'PINC0002', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'The Oriental Insurance Co. Ltd.', 'value' => 'The Oriental Insurance Co. Ltd.', 'partyid' => 'PINC0003', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'United India Insurance Co. Ltd.', 'value' => 'United India Insurance Co. Ltd.', 'partyid' => 'PINC0004', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Bajaj Allianz General Insurance Co. Ltd.', 'value' => 'Bajaj Allianz General Insurance Co. Ltd.', 'partyid' => 'PINC0005', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'ICICI Lombard General Insurance Co. Ltd.', 'value' => 'ICICI Lombard General Insurance Co. Ltd.', 'partyid' => 'PINC0006', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'IFFCO Tokio General Insurance Co. Ltd.', 'value' => 'IFFCO Tokio General Insurance Co. Ltd.', 'partyid' => 'PINC0007', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Reliance General Insurance Co. Ltd.', 'value' => 'Reliance General Insurance Co. Ltd.', 'partyid' => 'PINC0008', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Royal Sundaram General Insurance Co. Limited', 'value' => 'Royal Sundaram General Insurance Co. Limited', 'partyid' => 'PINC0009', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Tata AIG General Insurance Co. Ltd.', 'value' => 'Tata AIG General Insurance Co. Ltd.', 'partyid' => 'PINC0010', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Cholamandalam MS General Insurance Co. Ltd.', 'value' => 'Cholamandalam MS General Insurance Co. Ltd.', 'partyid' => 'PINC0011', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'HDFC ERGO General Insurance Co. Ltd.', 'value' => 'HDFC ERGO General Insurance Co. Ltd.', 'partyid' => 'PINC0012', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'name' => 'Export Credit Guarantee Corporation of India Ltd.', 'value' => 'Export Credit Guarantee Corporation of India Ltd.', 'partyid' => 'PINC0013', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Agriculture Insurance Co. of India Ltd.', 'value' => 'Agriculture Insurance Co. of India Ltd.', 'partyid' => 'PINC0014', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'name' => 'Star Health and Allied Insurance Company Limited', 'value' => 'Star Health and Allied Insurance Company Limited', 'partyid' => 'PINC0015', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Apollo Munich Health Insurance Company Limited', 'value' => 'Apollo Munich Health Insurance Company Limited', 'partyid' => 'PINC0016', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Future Generali India Insurance Company Limited', 'value' => 'Future Generali India Insurance Company Limited', 'partyid' => 'PINC0017', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'name' => 'Universal Sompo General Insurance Co. Ltd.', 'value' => 'Universal Sompo General Insurance Co. Ltd.', 'partyid' => 'PINC0018', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'name' => 'Shriram General Insurance Company Limited', 'value' => 'Shriram General Insurance Company Limited', 'partyid' => 'PINC0019', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Bharti AXA General Insurance Company Limited', 'value' => 'Bharti AXA General Insurance Company Limited', 'partyid' => 'PINC0020', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'Raheja QBE General Insurance Company Limited', 'value' => 'Raheja QBE General Insurance Company Limited', 'partyid' => 'PINC0021', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'name' => 'SBI General Insurance Company Limited', 'value' => 'SBI General Insurance Company Limited', 'partyid' => 'PINC0022', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'name' => 'Max Bupa Health Insurance Company Ltd.', 'value' => 'Max Bupa Health Insurance Company Ltd.', 'partyid' => 'PINC0023', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'name' => 'HDFC General Insurance Company Limited', 'value' => 'HDFC General Insurance Company Limited', 'partyid' => 'PINC0024', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'name' => 'Religare Health Insurance Company Limited', 'value' => 'Religare Health Insurance Company Limited', 'partyid' => 'PINC0025', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 26, 'name' => 'Magma HDI General Insurance Company Limited', 'value' => 'Magma HDI General Insurance Company Limited', 'partyid' => 'PINC0026', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 27, 'name' => 'Liberty General Insurance Ltd', 'value' => 'Liberty General Insurance Ltd', 'partyid' => 'PINC0027', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 28, 'name' => 'Cigna TTK Health Insurance Company Ltd.', 'value' => 'Cigna TTK Health Insurance Company Ltd.', 'partyid' => 'PINC0028', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 29, 'name' => 'Kotak Mahindra General Insurance Company Limited', 'value' => 'Kotak Mahindra General Insurance Company Limited', 'partyid' => 'PINC0029', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'name' => 'Aditya Birla Health Insurance Co. Limited', 'value' => 'Aditya Birla Health Insurance Co. Limited', 'partyid' => 'PINC0030', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'name' => 'DHFL General Insurance Limited', 'value' => 'DHFL General Insurance Limited', 'partyid' => 'PINC0031', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'name' => 'ACKO General Insurance Limited', 'value' => 'ACKO General Insurance Limited', 'partyid' => 'PINC0032', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 33, 'name' => 'Digit General Insurance Limited', 'value' => 'Digit General Insurance Limited', 'partyid' => 'PINC0033', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 34, 'name' => 'Edelweiss General Insurance Company Limited', 'value' => 'Edelweiss General Insurance Company Limited', 'partyid' => 'PINC0034', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'name' => 'Kotak Mahindra Health Insurance Company Limited', 'value' => 'Kotak Mahindra Health Insurance Company Limited', 'partyid' => 'PINC0035', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'name' => 'Aditya Birla Health Insurance Company Limited', 'value' => 'Aditya Birla Health Insurance Company Limited', 'partyid' => 'PINC0036', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 37, 'name' => 'Care Health Insurance Limited', 'value' => 'Care Health Insurance Limited', 'partyid' => 'PINC0037', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pa_insurance_masters');
    }
}
