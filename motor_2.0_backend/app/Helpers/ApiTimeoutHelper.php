<?php

namespace App\Helpers;

use App\Models\ApiTimeoutAutoScale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ApiTimeoutHelper
{
    protected static $defaultApiTimeout = 45;

    /**
     * This function will trigger slack notification to a configured channel if specific IC's API is timedout
     * @param Curl $curlObject Ixudra\Curl\Facades\Curl curl's response object
     * @param Array $logData Webservice table's row record which is inserted
     * @param Int $logRowId The id of the row which is inserted in the webservice table
     * @return void
     */
    public function monitorIcApi($curlObject, $logData, $logRowId): void
    {
        try {
            if (self::isIcApiTimedOut($curlObject)) {
                self::triggerNotification($logData, $logRowId);
                self::autoScaleTimeout($logData);
            } else {
                self::downGradeApiTimeout($logData);
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    /**
     * Trigger slack notification to a configured channel if the specific IC's API is timedout
     * @param Array $logData Webservice table's row record which is inserted
     * @param Int $logRowId The id of the row which is inserted in the webservice table
     * @return void
     */
    public function triggerNotification($logData, $logRowId): void
    {
        if (getCommonConfig('slack.IcTimeoutNotification.isEnabled') != 'Y' && !empty($notificationURL = getCommonConfig('slack.IcTimeoutNotification.channel.url'))) {
            return;
        }
        $details = $logData;
        $details['log_id'] = $logRowId;
        unset($details['request'], $details['response'], $details['headers']);
        Notification::route('slack', $notificationURL)->notify(new \App\Notifications\IcTimeoutNotification($details));
    }

/**
 * Get IC API timeout by transactionType basis
 * @param String $ic_alias Company alias of IC
 * @param String $transactionType values will be either 'quote' or 'proposal'. Default will be 'proposal'
 * @return int This timeout value will be cached for 1 hour
 */
    public function getIcApiTimeout($ic_alias, $transactionType = 'proposal', $endpoint_url = '')
    {
        $unique_name = $this->getUniqueRecordName([
            'company' => $ic_alias,
            'transaction_type' => $transactionType,
            'endpoint_url' => $endpoint_url,
        ]);
        return Cache::remember($unique_name, 3600, function () use ($transactionType, $ic_alias, $unique_name) {
            $timeout = self::$defaultApiTimeout;
            if ($timeout = ApiTimeoutAutoScale::where([
                'unique_record' => $unique_name,
            ])->select('timeout')->get()->pluck('timeout')->first()) {
            } else if (($timeout = getCommonConfig('global.timeout.' . $transactionType . 'Page.' . $ic_alias)) > 0) {
            } else if (($timeout = getCommonConfig('global.timeout.' . $transactionType . 'Page')) > 0) {
            }
            return (int) $timeout;
        });
    }

/**
 * AutoScale the timeout of the IC where the timeout has breached the threshold limit
 * @param Array $rowData An array of webservice table's row which is inserted after the IC's API call
 * @return void The autoScaled timeOut value is stored in another table and cached
 */
    public function autoScaleTimeout($rowData): void
    {
        if (self::isAutoScaleEnabled()) {
            // IC API timeOut is crossed, need to upscale the same and remove the existing one from cache. And set new timeout.
            $md5_unique_record = $this->getUniqueRecordName($rowData);
            $existing_timeout = $rowData['response_time'];
            $scaled_time = (int) ($existing_timeout + self::getAutoScaleIncrementTime());
            ApiTimeoutAutoScale::updateOrCreate([
                'unique_record' => $md5_unique_record,
            ], [
                'endpoint_url' => $rowData['endpoint_url'],
                'unique_record' => $md5_unique_record,
                'company_alias' => $rowData['company'],
                'transaction_type' => $rowData['transaction_type'],
                'timeout' => $scaled_time,
            ]);
            Cache::forget($md5_unique_record);
            self::setTimeoutIntoCache($md5_unique_record, $scaled_time);
        }
    }

    /**
     * If the IC's API is working fine, then we need to downgrade the scaled time limit.
     * @param Array $rowData An array of webservice table's row which is inserted after the IC's API call
     */
    public function downGradeApiTimeout($rowData): void
    {
        if (!self::isAutoScaleEnabled()) {
            return;
        }
        $unique_name = $this->getUniqueRecordName($rowData);
        // Check if the IC API is already upscaled or not
        if (!(ApiTimeoutAutoScale::where([
            'unique_record' => $unique_name,
        ])->exists())) {
            return;
        }
        $cache_name = $unique_name;
        $existing_timeout = $rowData['response_time'];
        $down_graded_time = (int) ($existing_timeout - self::getAutoScaleIncrementTime());
        // Check if the decremented value is less than the actual IC timeout
        $ic_timeout = getCommonConfig('global.timeout.' . $rowData['transaction_type'] . 'Page.' . $rowData['company'], 0);
        $ic_timeout = $ic_timeout > 0 ? $ic_timeout : 0;
        $global_timeout = getCommonConfig('global.timeout.' . $rowData['transaction_type'] . 'Page', 0);
        $global_timeout = $global_timeout > 0 ? $global_timeout : 0;
        if ($ic_timeout > 0 && $down_graded_time <= $ic_timeout) {
            ApiTimeoutAutoScale::where([
                'unique_record' => $unique_name,
            ])->delete();
            self::forgetTimeoutFromCache($cache_name);
            return;
            // Check if the decremented value is less than the global timeout
        } else if ($global_timeout > 0 && $down_graded_time <= $global_timeout) {
            ApiTimeoutAutoScale::where([
                'unique_record' => $unique_name,
            ])->delete();
            self::forgetTimeoutFromCache($cache_name);
            return;
        }

        ApiTimeoutAutoScale::updateOrCreate([
            'unique_record' => $unique_name,
        ], [
            'endpoint_url' => $rowData['endpoint_url'],
            'unique_record' => $unique_name,
            'company_alias' => $rowData['company'],
            'transaction_type' => $rowData['transaction_type'],
            'timeout' => $down_graded_time,
        ]);
        self::forgetTimeoutFromCache($cache_name);
        self::setTimeoutIntoCache($cache_name, $down_graded_time);

    }

    /**
     * Check if the autoscale is enabled or not
     * @return Boolean
     */
    public function isAutoScaleEnabled(): bool
    {
        return getCommonConfig('global.timeout.autoScale.isEnabled') == 'Y';
    }

    /**
     * Get the Auto Scale Increment time configured by the user (in seconds)
     * If the value is not configured then the default value will be 10 seconds
     * @return Int
     */
    public function getAutoScaleIncrementTime(): int
    {
        $time = (int) getCommonConfig('global.timeout.autoScaleBy', 10);
        return $time > 0 ? $time : 10;
    }

    /**
     * Check if the IC's API is timedout or not
     * @param Curl $curlObject Ixudra\Curl\Facades\Curl curl's response object
     * @return Bool
     */
    public function isIcApiTimedOut($curlObject): bool
    {
        // curl: (28) Connection timed out after x milliseconds
        // curl: (28) Operation timed out after y milliseconds with 0 bytes received
        return !empty($curlObject->error ?? '') && \Illuminate\Support\Str::contains($curlObject->error ?? '', 'timed out after');
    }

    protected function setTimeoutIntoCache($cacheName, $timeout)
    {
        Cache::remember($cacheName, 3600, function () use ($timeout) {
            return $timeout;
        });
    }

    protected function forgetTimeoutFromCache($cache_name)
    {
        Cache::forget($cache_name);
    }

    /**
     * Get a Unique md5 string on the basis of transaction_type, company and endpoint_url
     * @param Array $rowData consists of transaction_type, company and endpoint_url
     * @return string MD5 string
     */
    protected function getUniqueRecordName($rowData)
    {
        $endpoint_url = explode('?', ($rowData['endpoint_url'] ?? ''))[0];
        $unique_name = request()->header('host') . $rowData['company'] . $rowData['transaction_type'] . $endpoint_url;
        //32 characters : MD5 the name for unique record
        return md5($unique_name);
    }
}
