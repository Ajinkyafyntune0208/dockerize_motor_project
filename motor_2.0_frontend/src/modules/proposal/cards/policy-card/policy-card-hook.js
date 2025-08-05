/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";
import { PrevIc, SaveAddon } from "modules/proposal/proposal.slice";
import { useDispatch } from "react-redux";
import moment from "moment";
import { TypeReturn } from "modules/type";
import { differenceInDays } from "date-fns";
import { toDate } from "utils";

//prefill Api
export const usePrefillPolicyCard = (
  prepolicy,
  CardData,
  reset,
  expiryDate
) => {
  useEffect(() => {
    if (_.isEmpty(prepolicy) && !_.isEmpty(CardData?.prepolicy)) {
      reset(
        CardData?.prepolicy.prevPolicyExpiryDate === expiryDate && expiryDate
          ? CardData?.prepolicy
          : { ...CardData?.prepolicy, prevPolicyExpiryDate: expiryDate }
      );
    }
  }, [CardData.prepolicy, expiryDate]);
};

//Load Previous IC List
export const useLoadPreviousIcList = (
  companyAlias,
  prevPolicyCon,
  fields,
  enquiry_id,
  OwnDamage
) => {
  const dispatch = useDispatch();
  useEffect(() => {
    if (companyAlias)
      (prevPolicyCon || fields.includes("cpaOptOut")) &&
        dispatch(
          PrevIc({
            companyAlias: companyAlias,
            enquiryId: enquiry_id,
          })
        );
    prevPolicyCon &&
      OwnDamage &&
      dispatch(
        PrevIc(
          {
            companyAlias: companyAlias,
            enquiryId: enquiry_id,
            isTp: true,
          },
          true
        )
      );
  }, [companyAlias]);
};

//resetting cpa details
export const useResetCpaDetails = (
  prepolicy,
  CardData,
  reasonCpa,
  setValue
) => {
  useEffect(() => {
    if (
      (!_.isEmpty(prepolicy) || !_.isEmpty(CardData?.prepolicy)) &&
      reasonCpa &&
      reasonCpa === "I do not have a valid driving license."
    ) {
      setValue("cPAPolicyNo", "");
      setValue("cPAPolicyFmDt", "");
      setValue("cPAPolicyToDt", "");
      setValue("cPASumInsured", "");
      setValue("cpaPolicyNumber", "");
      setValue("cpaPolicyStartDate", "");
      setValue("cpaPolicyEndDate", "");
      setValue("cpaSumInsured", "");
    }
  }, [reasonCpa]);
};

//TP Policy End Date Calculation  i.e  3yr - 1d for Bike & 5yr - 1d for car
export const useTpPolicyEndDateCalculation = (
  TPStartDate,
  temp_data,
  type,
  setValue
) => {
  useEffect(() => {
    if (
      TPStartDate &&
      (!temp_data?.userProposal?.tpEndDate ||
        !(
          import.meta.env.VITE_BROKER === "RB" &&
          temp_data?.selectedQuote?.isRenewal === "Y"
        ))
    ) {
      const TpEndDate = TPStartDate
        ? moment(TPStartDate, "DD-MM-YYYY")
            .add(TypeReturn(type) === "bike" ? 5 : 3, "years")
            .subtract(1, "days")
        : false;
      setValue("tpEndDate", moment(TpEndDate).format("DD-MM-YYYY"));
    } else {
      if (
        TPStartDate &&
        temp_data?.userProposal?.tpEndDate &&
        import.meta.env.VITE_BROKER === "RB" &&
        temp_data?.selectedQuote?.isRenewal === "Y"
      ) {
        setValue("tpEndDate", temp_data?.userProposal?.tpEndDate);
      }
    }
  }, [TPStartDate]);
};

//fastLane prefill
export const useFastLanePrefill = (CardData, temp_data, setValue) => {
  useEffect(() => {
    if (_.isEmpty(CardData?.prepolicy)) {
      temp_data?.userProposal?.previousInsuranceCompany &&
        setValue(
          "previousInsuranceCompany",
          temp_data?.userProposal?.previousInsuranceCompany
        );
      temp_data?.userProposal?.previousPolicyNumber &&
        setValue(
          "previousPolicyNumber",
          temp_data?.userProposal?.previousPolicyNumber
        );
      temp_data?.userProposal?.tpInsuranceNumber &&
        setValue(
          "tpInsuranceNumber",
          temp_data?.userProposal?.tpInsuranceNumber
        );
      temp_data?.userProposal?.tpInsuranceCompany &&
        setValue(
          "tpInsuranceCompany",
          temp_data?.userProposal?.tpInsuranceCompany
        );
      temp_data?.userProposal?.tpInsuranceCompanyName &&
        setValue(
          "tpInsuranceCompanyName",
          temp_data?.userProposal?.tpInsuranceCompanyName
        );
      temp_data?.userProposal?.tpStartDate &&
        setValue("tpStartDate", temp_data?.userProposal?.tpStartDate);
    }
  }, [CardData?.prepolicy]);
};

//Setting Policy Expiry Date
export const useSetPreviousPolicyExpiryDate = (expiryDate, setValue) => {
  useEffect(() => {
    if (expiryDate) {
      setValue("prevPolicyExpiryDate", expiryDate);
    }
  }, [expiryDate]);
};

export const useSetCpaReason = (
  ReasonIP,
  PACon,
  setReasonCpa,
  enquiry_id,
  temp_data,
  dispatch
) => {
  useEffect(() => {
    if (ReasonIP && PACon) {
      setReasonCpa(ReasonIP);
      dispatch(
        SaveAddon({
          enquiryId: enquiry_id,
          lastProposalModifiedTime: temp_data?.lastProposalModifiedTime,
          addonData: {
            compulsory_personal_accident: [{ reason: ReasonIP }],
          },
          isProposal: true,
        })
      );
    }
  }, [ReasonIP, PACon]);
};

export const useSetPreviousInsurerCompany = (
  IcName,
  setValue,
  type,
  ODlastYr,
  watch
) => {
  useEffect(() => {
    if (!_.isEmpty(IcName)) {
      setValue("InsuranceCompanyName", IcName[0]?.name);
      TypeReturn(type) !== "cv" &&
        ODlastYr &&
        watch("disabled_id") !== IcName[0]?.name &&
        setValue("disabled_id", IcName[0]?.name);
    }
  }, [IcName]);
};

export const useSetCpaInsurerCompany = (CpaIcName, setValue) => {
  useEffect(() => {
    if (!_.isEmpty(CpaIcName))
      setValue("CpaInsuranceCompany", CpaIcName[0]?.name);
  }, [CpaIcName]);
};

export const useSetTpInsurerCompanyName = (TpIcName, setValue) => {
  useEffect(() => {
    if (!_.isEmpty(TpIcName))
      setValue("tpInsuranceCompanyName", TpIcName[0]?.name);
  }, [TpIcName]);
};

export const useSetTpStartDate = (
  temp_data,
  CardData,
  TPStartDate,
  setValue
) => {
  useEffect(() => {
    if (
      !_.isEmpty(temp_data) &&
      _.isEmpty(CardData?.prepolicy) &&
      !TPStartDate
    ) {
      setValue(
        "tpStartDate",
        temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate
      );
    }
  }, [temp_data]);
};

export const useSetTpInsurerNumber = (
  temp_data,
  PolicyValidationExculsion,
  setValue,
  previousPolicyNumber
) => {
  useEffect(() => {
    if (
      differenceInDays(
        toDate(moment().format("DD-MM-YYYY")),
        toDate(temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate) <=
          365
      ) &&
      !PolicyValidationExculsion &&
      !temp_data?.userProposal?.tpInsuranceNumber
    ) {
      setValue("tpInsuranceNumber", previousPolicyNumber);
    }
  }, [previousPolicyNumber]);
};

export const useSetPreviousPolicyStartDate = (
  temp_data,
  startDate,
  setValue
) => {
  useEffect(() => {
    temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
      startDate &&
      setValue("previousPolicyStartDate", startDate);
  }, [startDate]);
};
