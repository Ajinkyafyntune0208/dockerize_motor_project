<?php

namespace App\Helpers\IcHelpers;

use Illuminate\Support\Facades\DB;

class RelianceHelper
{
    public static function getRtoAndRcDetail($rcNumber, $rtoCode, $isNewBusiness, $other = [])
    {
        $isRegPresent = !empty($rcNumber) && strtoupper($rcNumber) != 'NEW';

        $changeRcNumber = true;
        $check = false;

        if (!$isRegPresent && !$isNewBusiness) {

            $rtoData = self::getRtoMasterData($rtoCode);

            if (empty($rtoData)) {
                $rtoCode = RtoCodeWithOrWithoutZero($rtoCode, true);

                $rtoData = self::getRtoMasterData($rtoCode);

                if (empty($rtoData)) {
                    return [
                        'status' => false,
                        'message' => self::getRtoErrorMessage(),
                    ];
                }
            }

            return [
                'status' => true,
                'message' => self::getRtoSuccessMessage(),
                'rtoCode' => $rtoCode,
                'rtoData' => $rtoData,
                'rcNumber' => $rtoCode . $other['appendRegNumber'],
            ];
        }

        if ($isNewBusiness) {

            $rtoData = self::getRtoMasterData($rtoCode);

            if (empty($rtoData)) {
                $rtoCode = RtoCodeWithOrWithoutZero($rtoCode, true);

                $rtoData = self::getRtoMasterData($rtoCode);

                if (empty($rtoData)) {
                    return [
                        'status' => false,
                        'message' => self::getRtoErrorMessage()
                    ];
                }
            }

            return [
                'status' => true,
                'message' => self::getRtoSuccessMessage(),
                'rtoCode' => $rtoCode,
                'rtoData' => $rtoData,
                'rcNumber' => 'NEW',
            ];
        } else {
            $explodedRegNumber = explode('-', $rcNumber);
            $isDlRto = strtoupper($explodedRegNumber[0]) == 'DL';
            if (is_numeric($explodedRegNumber[1])) {
                if ($isDlRto) {
                    if (strlen($explodedRegNumber[1]) == 1) {
                        if (strlen($explodedRegNumber[2]) >= 2) {
                            $rtoCode = $explodedRegNumber[0] . '-' . $explodedRegNumber[1] . substr($explodedRegNumber[2], 0, 1);
                            $explodedRto = explode('-', $rtoCode);
                            $regNumber = $explodedRto;
                            $regNumber[2] = substr($explodedRegNumber[2], 1);
                            $regNumber[3] = $explodedRegNumber[3];
                            $explodedRegNumber = $regNumber;
                            $changeRcNumber = false;
                            $check = true;
                        } else {
                            $changeRcNumber = false;
                            $rtoCode = $explodedRegNumber[0] . '-' . $explodedRegNumber[1];

                            if (empty(self::getRtoMasterData($rtoCode))) {
                                $rtoCode = RtoCodeWithOrWithoutZero($explodedRegNumber[0] . '-' . $explodedRegNumber[1], true);
                            }
                        }
                    } else {
                        $rtoCode = $explodedRegNumber[0] . '-' . $explodedRegNumber[1];

                        if (empty(self::getRtoMasterData($rtoCode))) {
                            $rtoCode = RtoCodeWithOrWithoutZero($explodedRegNumber[0] . '-' . $explodedRegNumber[1], true);
                        }
                    }
                } else {
                    $rtoCode = $explodedRegNumber[0] . '-' . $explodedRegNumber[1];

                    if (empty(self::getRtoMasterData($rtoCode))) {
                        $rtoCode = RtoCodeWithOrWithoutZero($explodedRegNumber[0] . '-' . $explodedRegNumber[1], true);
                    }
                }
            } else {
                $rtoCode = $explodedRegNumber[0] . '-' . $explodedRegNumber[1];
            }

            $rtoData =self::getRtoMasterData($rtoCode);

            if (empty($rtoData) && !$isDlRto) {
                return [
                    'status' => false,
                    'message' => self::getRtoErrorMessage(),
                ];
            }

            if (empty($rtoData)) {
                $explodedRto = explode('-', $rtoCode);
                if (!is_numeric($explodedRto[1]) && strlen($explodedRto[1]) >= 2) {
                    $rtoCode = 'DL-' . substr($explodedRto[1], 0, 1);

                    if (empty(self::getRtoMasterData($rtoCode))) {
                        $rtoCode = 'DL-0' . substr($explodedRto[1], 0, 1);
                    }
                    $newExplodedRto = explode('-', $rtoCode);
                    $explodedRegNumber[1] = $newExplodedRto[1];
                    $explodedRegNumber[2] = substr($explodedRto[1], 1) . $explodedRegNumber[2];
                    $rtoData = self::getRtoMasterData($rtoCode);

                    if (empty($rtoData)) {
                        return [
                            'status' => false,
                            'message' => self::getRtoErrorMessage(),
                        ];
                    }

                    if (!$changeRcNumber) {
                        $explodedRegNumber = explode('-', $rcNumber);
                    }
                }
            } elseif ($isDlRto) {
                if ($rtoCode != $explodedRegNumber[0] . '-' . $explodedRegNumber[1]) {
                    $explodedRto = explode('-', $rtoCode);
                    $regNumber = $explodedRto;
                    $regNumber[2] = $explodedRegNumber[2];
                    $regNumber[3] = $explodedRegNumber[3];
                    $explodedRegNumber = $regNumber;
                }

                if (!$changeRcNumber && !$check) {
                    $explodedRegNumber = explode('-', $rcNumber);
                }
            }

            $rcNumber = implode('-', $explodedRegNumber);
        }

        return [
            'status' => true,
            'message' => self::getRtoSuccessMessage(),
            'rtoCode' => $rtoCode,
            'rtoData' => $rtoData,
            'rcNumber' => $rcNumber,
        ];
    }

    public static function getRtoMasterData($rtoCode)
    {
        return DB::table('reliance_rto_master as rm')
                ->where('rm.region_code', $rtoCode)
                ->select('rm.*')
                ->first();
    }

    public static function getRtoErrorMessage()
    {
        return 'RTO details not found';
    }

    public static function getRtoSuccessMessage()
    {
        return 'RTO details found';
    }
}
