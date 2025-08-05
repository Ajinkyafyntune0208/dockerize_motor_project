import React, { useEffect, useState } from "react";
import _ from "lodash";
import swal from "sweetalert";
import { useHistory } from "react-router";
import { useSelector, useDispatch } from "react-redux";
import { Wording, clear } from "../../proposal.slice";
import CompactcardCompo from "./compact-card";
import PropTypes from "prop-types";
import { fetchToken } from "utils";
import { useLocation } from "react-router";

const InfoCard = ({
  selectedQuote,
  enquiry_id,
  Additional: unfilteredAdditional,
  type,
  token,
  Theme,
  breakinCase,
  lessthan767,
  GenerateDulicateEnquiry,
  typeId,
  journey_type,
  icr,
}) => {
  const history = useHistory();
  const dispatch = useDispatch();
  const location = useLocation();
  const _stToken = fetchToken();
  const query = new URLSearchParams(location.search);
  const shared = query.get("shared");
  const { temp_data, checkAddon, wording } = useSelector(
    (state) => state.proposal
  );
  //IMT 23 to be shown as cover instead of an addon
  //filtering out imt 23 from addons and appending the it in accessories
  const Additional = {
    ...unfilteredAdditional,
    ...(!_.isEmpty(unfilteredAdditional?.applicableAddons) &&
      unfilteredAdditional?.applicableAddons && {
        applicableAddons: unfilteredAdditional?.applicableAddons.filter(
          (item) => item.name !== "IMT - 23"
        ),
        ...(!_.isEmpty(unfilteredAdditional?.additionalCovers) &&
        unfilteredAdditional?.additionalCovers
          ? {
              additionalCovers: [
                ...unfilteredAdditional?.additionalCovers,
                ...unfilteredAdditional?.applicableAddons.filter(
                  (item) => item.name === "IMT - 23"
                ),
              ],
            }
          : {
              additionalCovers: [
                ...unfilteredAdditional?.applicableAddons.filter(
                  (item) => item.name === "IMT - 23"
                ),
              ],
            }),
      }),
  };

  const addOnName = !_.isEmpty(checkAddon)
    ? checkAddon?.map(({ addon_name }) => addon_name)
    : [];
  const VehicleDetails = !_.isEmpty(selectedQuote?.mmvDetail)
    ? selectedQuote?.mmvDetail
    : {};
  const Accessories = !_.isEmpty(Additional?.accessories)
    ? _.compact(
        Additional?.accessories?.map((elem) => (elem?.sumInsured ? elem : null))
      )
    : [];

  const FilteredAccessories =
    !_.isEmpty(Accessories) && !_.isEmpty(addOnName)
      ? _.compact(
          Accessories?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];

  const AdditionalCovers = !_.isEmpty(Additional?.additionalCovers)
    ? _.compact(
        Additional?.additionalCovers?.map((elem) =>
          elem?.sumInsured * 1 ||
          elem?.sumInsured * 1 === 0 ||
          elem?.premium * 1 ||
          elem?.premium * 1 === 0 ||
          elem?.name === "Geographical Extension"
            ? elem
            : elem?.lLNumberCleaner ||
              elem?.lLNumberConductor ||
              elem?.lLNumberDriver
            ? elem
            : null
        )
      )
    : [];

  const FilteredAdditionalCovers =
    !_.isEmpty(AdditionalCovers) && !_.isEmpty(addOnName)
      ? _.compact(
          AdditionalCovers?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];
  const CPA = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident
    : [];

  const FilteredCPA =
    !_.isEmpty(CPA) && !_.isEmpty(addOnName)
      ? _.compact(
          CPA?.map((item) => (addOnName.includes(item.name) ? item : null))
        )
      : [];

  const Discounts = !_.isEmpty(Additional?.discounts)
    ? Additional?.discounts
    : [];

  const FilteredDiscounts =
    !_.isEmpty(Discounts) && !_.isEmpty(addOnName)
      ? _.compact(
          Discounts?.map((item) =>
            addOnName.includes(item.name) ? item : null
          )
        )
      : [];
  //Other Addons
  let others = temp_data?.selectedQuote?.addOnsData?.other
    ? Object.keys(
        _.isEmpty(
          Additional?.applicableAddons?.filter(
            (x) => x?.name === "Return To Invoice"
          )
        )
          ? _.omit(temp_data?.selectedQuote?.addOnsData?.other, [
              "fullInvoicePrice",
              "fullInvoicePriceInsuranceCost",
              "fullInvoicePriceRegCharges",
              "fullInvoicePriceRoadtax",
            ])
          : temp_data?.selectedQuote?.addOnsData?.other
      )
    : [];

  const quoteLog = !_.isEmpty(temp_data?.quoteLog) ? temp_data?.quoteLog : {};
  const [limiter, setLimiter] = useState(false);

  const redirectTo = () => {
    swal({
      title: "Confirm Action",
      text: ["Payment Initiated", "payment failed"].includes(
        ["payment failed"].includes(
          temp_data?.journeyStage?.stage.toLowerCase()
        )
          ? temp_data?.journeyStage?.stage.toLowerCase()
          : temp_data?.journeyStage?.stage
      )
        ? `Payment status is Incomplete. To edit the Proposal an update is required.`
        : `Are you sure you want to change insurer?`,
      icon: ["Payment Initiated", "payment failed"].includes(
        ["payment failed"].includes(
          temp_data?.journeyStage?.stage.toLowerCase()
        )
          ? temp_data?.journeyStage?.stage.toLowerCase()
          : temp_data?.journeyStage?.stage
      )
        ? "info"
        : "warning",
      buttons: {
        cancel: "Cancel",
        catch: {
          text: "Confirm",
          value: "confirm",
        },
      },
      dangerMode: true,
    }).then((caseValue) => {
      switch (caseValue) {
        case "confirm":
          ["Payment Initiated", "payment failed"].includes(
            ["payment failed"].includes(
              temp_data?.journeyStage?.stage.toLowerCase()
            )
              ? temp_data?.journeyStage?.stage.toLowerCase()
              : temp_data?.journeyStage?.stage
          )
            ? GenerateDulicateEnquiry()
            : history.push(
                `/${type}/quotes?enquiry_id=${enquiry_id}${
                  token ? `&xutm=${token}` : ``
                }${typeId ? `&typeid=${typeId}` : ``}${
                  journey_type ? `&journey_type=${journey_type}` : ``
                }${_stToken ? `&stToken=${_stToken}` : ``}${
                  shared ? `&shared=${shared}` : ``
                }`
              );
          break;
        default:
      }
    });
  };

  useEffect(() => {
    dispatch(clear("clear"));
    dispatch(clear("wording"));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!limiter && temp_data?.selectedQuote?.policyId) {
      dispatch(
        Wording({
          // policyId: temp_data?.selectedQuote?.policyId,
          policyId: enquiry_id,
          enquiryId: enquiry_id,
        })
      );
      setLimiter(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);

  /*-----mobile states-----*/
  const [vehicleInfo, showVehicleInfo] = useState(lessthan767 ? false : true);
  const [breakup, showBreakup] = useState(lessthan767 ? false : true);
  const [addonsInfo, showAddonsInfo] = useState(lessthan767 ? false : true);
  const [oAddonsInfo, showOAddonsInfo] = useState(lessthan767 ? false : true);
  const [coversInfo, showCoversInfo] = useState(lessthan767 ? false : true);
  const [accesInfo, showAccesInfo] = useState(lessthan767 ? false : true);
  const [discountInfo, showDiscountInfo] = useState(lessthan767 ? false : true);
  /*--x--mobile states--x--*/

  return (
    <CompactcardCompo
      temp_data={temp_data}
      breakinCase={breakinCase}
      type={type}
      icr={icr}
      Theme={Theme}
      redirectTo={redirectTo}
      selectedQuote={selectedQuote}
      quoteLog={quoteLog}
      VehicleDetails={VehicleDetails}
      lessthan767={lessthan767}
      showBreakup={showBreakup}
      showVehicleInfo={showVehicleInfo}
      vehicleInfo={vehicleInfo}
      breakup={breakup}
      Additional={Additional}
      showAddonsInfo={showAddonsInfo}
      addonsInfo={addonsInfo}
      others={others}
      showOAddonsInfo={showOAddonsInfo}
      oAddonsInfo={oAddonsInfo}
      FilteredAccessories={FilteredAccessories}
      accesInfo={accesInfo}
      showAccesInfo={showAccesInfo}
      FilteredAdditionalCovers={FilteredAdditionalCovers}
      FilteredCPA={FilteredCPA}
      showCoversInfo={showCoversInfo}
      coversInfo={coversInfo}
      FilteredDiscounts={FilteredDiscounts}
      showDiscountInfo={showDiscountInfo}
      discountInfo={discountInfo}
      wording={wording}
    />
  );
};

export default InfoCard;

InfoCard.propTypes = {
  selectedQuote: PropTypes.object,
  enquiry_id: PropTypes.string,
  Additional: PropTypes.object,
  type: PropTypes.string,
  token: PropTypes.string,
  Theme: PropTypes.object,
  breakinCase: PropTypes.bool,
  lessthan767: PropTypes.bool,
  GenerateDulicateEnquiry: PropTypes.func,
  typeId: PropTypes.string,
  journey_type: PropTypes.string,
  icr: PropTypes.string,
};
