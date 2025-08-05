import { CompactCard } from "components";
import React from "react";
import { ProposalRibbon } from "./info-style";
import DivisionContent from "./div-content";
import SelectedAddons from "./addonsAndOthers/selected-addons";
import OtherAddons from "./addonsAndOthers/other-addons";
import SelectedAccessories from "./addonsAndOthers/selected-accessories";
import AdditionalCover from "./addonsAndOthers/additional-cover";
import Discount from "./addonsAndOthers/discount";
import DownloadBtn from "./component/download-btn";
import _ from "lodash";
import { useDispatch } from "react-redux";
import { useEffect } from "react";
import { ShortlenUrl } from "modules/quotesPage/quote.slice";
import OtherCovers from "./addonsAndOthers/other-covers";

const CompactcardCompo = ({
  temp_data,
  breakinCase,
  type,
  icr,
  Theme,
  redirectTo,
  selectedQuote,
  quoteLog,
  VehicleDetails,
  lessthan767,
  showBreakup,
  showVehicleInfo,
  vehicleInfo,
  breakup,
  Additional,
  showAddonsInfo,
  addonsInfo,
  others,
  showOAddonsInfo,
  oAddonsInfo,
  FilteredAccessories,
  accesInfo,
  showAccesInfo,
  FilteredAdditionalCovers,
  FilteredCPA,
  showCoversInfo,
  coversInfo,
  FilteredDiscounts,
  showDiscountInfo,
  discountInfo,
  wording,
}) => {
  const url = window.location.href;
  const dispatch = useDispatch();

  useEffect(() => {
    if (!_.isEmpty(url)) {
      dispatch(ShortlenUrl({ url: url }));
    }
  }, [dispatch, url]);

  const isAddOnNotEmpty = (addOn) => addOn && !_.isEmpty(Object.keys(addOn));

  const hasLLPaidDriver = () => {
    const otherAddOns = temp_data?.selectedQuote?.addOnsData?.other;
    return (
      otherAddOns &&
      isAddOnNotEmpty(otherAddOns) &&
      Object.keys(otherAddOns).includes("lLPaidDriver")
    );
  };

  const FilteredAccessoriesWithLLPaidDriver = [
    ...(FilteredAccessories || []),
    hasLLPaidDriver() ? { name: "LL Paid Driver" } : "",
  ];

  const isFilteredAccessoriesNotEmpty = !_.isEmpty(
    _.compact(FilteredAccessoriesWithLLPaidDriver)
  );

  return (
    <CompactCard
      style={
        temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" ||
        temp_data?.quoteLog?.premiumJson?.gdd === "Y"
          ? { position: "relative" }
          : {}
      }
      removeBottomHeader={"true"}
    >
      {temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" ? (
        <ProposalRibbon>Renewal Quote</ProposalRibbon>
      ) : temp_data?.quoteLog?.premiumJson?.gdd === "Y" ? (
        <ProposalRibbon>Pay As You Drive</ProposalRibbon>
      ) : (
        <noscript />
      )}
      {temp_data?.quoteLog?.premiumJson?.ribbon ? (
        <ProposalRibbon>
          {temp_data?.quoteLog?.premiumJson?.ribbon}
        </ProposalRibbon>
      ) : (
        <noscript />
      )}
      <div className="px-2">
        <DivisionContent
          breakinCase={breakinCase}
          type={type}
          icr={icr}
          Theme={Theme}
          redirectTo={redirectTo}
          selectedQuote={selectedQuote}
          quoteLog={quoteLog}
          temp_data={temp_data}
          VehicleDetails={VehicleDetails}
          lessthan767={lessthan767}
          showBreakup={showBreakup}
          showVehicleInfo={showVehicleInfo}
          vehicleInfo={vehicleInfo}
          breakup={breakup}
        />
        {!_.isEmpty(Additional?.applicableAddons) && (
          <SelectedAddons
            lessthan767={lessthan767}
            showAddonsInfo={showAddonsInfo}
            addonsInfo={addonsInfo}
            Additional={Additional}
            Theme={Theme}
            selectedQuote={selectedQuote}
          />
        )}
        {others &&
          !_.isEmpty(
            _.compact(
              others?.map((item) => (item === "lLPaidDriver" ? false : item))
            )
          ) && (
            <OtherAddons
              lessthan767={lessthan767}
              showOAddonsInfo={showOAddonsInfo}
              oAddonsInfo={oAddonsInfo}
              addonsInfo={addonsInfo}
              others={others}
              Theme={Theme}
              temp_data={temp_data}
            />
          )}
        {/*Acc*/}
        {isFilteredAccessoriesNotEmpty && (
          <SelectedAccessories
            lessthan767={lessthan767}
            showAccesInfo={showAccesInfo}
            accesInfo={accesInfo}
            FilteredAccessories={FilteredAccessories}
            temp_data={temp_data}
            Theme={Theme}
          />
        )}
        {/*Additional Covers*/}
        {(!_.isEmpty(FilteredAdditionalCovers) ||
          (!_.isEmpty(FilteredCPA)
            ? FilteredCPA[0]?.name
              ? true
              : false
            : false)) && (
          <AdditionalCover
            lessthan767={lessthan767}
            showCoversInfo={showCoversInfo}
            coversInfo={coversInfo}
            FilteredAdditionalCovers={FilteredAdditionalCovers}
            Theme={Theme}
            temp_data={temp_data}
            FilteredCPA={FilteredCPA}
          />
        )}
        {/*Other Covers*/}
        {temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" &&
          temp_data?.selectedQuote?.otherCovers?.legalLiabilityToEmployee !==
            undefined && (
            <OtherCovers
              lessthan767={lessthan767}
              Theme={Theme}
              temp_data={temp_data}
            />
          )}
        {/*Discounts*/}
        {!_.isEmpty(FilteredDiscounts) ||
        quoteLog?.premiumJson?.tppdDiscount * 1 ? (
          <Discount
            lessthan767={lessthan767}
            showDiscountInfo={showDiscountInfo}
            discountInfo={discountInfo}
            FilteredDiscounts={FilteredDiscounts}
            Theme={Theme}
            quoteLog={quoteLog}
          />
        ) : (
          <noscript />
        )}
        {wording?.pdfUrl ? (
          <DownloadBtn
            wording={wording}
            Theme={Theme}
          />
        ) : (
          <noscript />
        )}
      </div>
    </CompactCard>
  );
};

export default CompactcardCompo;
