import { useEffect, useMemo, useState } from "react";
import {
  FetchBranch,
  CarPincode,
  clear,
  branchMaster as setBranchData,
  getColor,
} from "../../proposal.slice";
import _ from "lodash";
import { TypeReturn } from "modules/type";
import swal from "sweetalert";
import { productType } from "modules/proposal/form-section/proposal-logic";
import { _haptics, reloadPage } from "utils";
import { acceptedBrokers, icWithColorMaster } from "./constants";

export const useBranchMaster = (
  dispatch,
  temp_data,
  branchMaster,
  financerCode
) => {
  useMemo(() => {
    if (
      !_.isEmpty(temp_data) &&
      financerCode &&
      temp_data?.selectedQuote?.companyAlias === "united_india"
    ) {
      setBranchData([]);
      dispatch(
        FetchBranch({
          companyAlias: "united_india",
          financierCode: financerCode,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data, financerCode]);
};

export const useFetchIcBaseValidation = (
  validation,
  temp_data,
  setValidations
) => {
  useEffect(() => {
    if (!_.isEmpty(validation)) {
      if (
        temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo ===
        "NEW"
      ) {
        setValidations(
          _.compact(
            validation?.map(
              (item) =>
                item?.IcName === temp_data?.selectedQuote?.companyAlias &&
                item?.NEW
            )
          )[0]
        );
      } else {
        setValidations(
          _.compact(
            validation?.map(
              (item) =>
                item?.IcName === temp_data?.selectedQuote?.companyAlias &&
                item?.Rollover
            )
          )[0]
        );
      }
    } else {
      setValidations(null);
    }
  }, [validation]);
};

export const usePrefillApi = (
  vehicle,
  CardData,
  reset,
  temp_data,
  regNo,
  setFinanceValidation,
  setAddValidation
) => {
  useEffect(() => {
    if (_.isEmpty(vehicle) && !_.isEmpty(CardData?.vehicle)) {
      reset(
        temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo[0] * 1
          ? { ...CardData?.vehicle }
          : temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo ===
            "NEW"
          ? vehicle?.regNo1 ===
            temp_data?.corporateVehiclesQuoteRequest?.rtoCode
            ? CardData?.vehicle
            : {
                ...CardData?.vehicle,
                regNo1: temp_data?.corporateVehiclesQuoteRequest?.rtoCode,
              }
          : CardData?.vehicle?.vehicaleRegistrationNumber === regNo
          ? {
              ...CardData?.vehicle,
              registrationDate:
                temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
                temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
            }
          : {
              ...CardData?.vehicle,
              vehicaleRegistrationNumber: regNo,
              regNo1: temp_data?.regNo1 || temp_data?.rtoNumber,
              regNo2:
                temp_data?.regNo2 ||
                (regNo !== "NEW" && CardData?.vehicle?.regNo2
                  ? CardData?.vehicle?.regNo2
                  : ""),
              regNo3:
                temp_data?.regNo3 ||
                (regNo !== "NEW" && CardData?.vehicle?.regNo3
                  ? CardData?.vehicle?.regNo3
                  : ""),
              registrationDate:
                temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
                temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
            }
      );
      setFinanceValidation(CardData?.isVehicleFinance);
      setAddValidation(CardData?.isCarRegistrationAddressSame);
    }
  }, [CardData.vehicle]);
};

export const useFetchPinCode = (
  PinCode,
  dispatch,
  companyAlias,
  enquiry_id
) => {
  useEffect(() => {
    if (PinCode?.length === 6 && companyAlias) {
      dispatch(
        CarPincode({
          companyAlias: companyAlias,
          pincode: PinCode,
          enquiryId: enquiry_id,
        })
      );
    } else {
      dispatch(clear("car_pincode"));
    }
  }, [PinCode]);
};

export const useSetPinCodeAndState = (pin, setValue) => {
  useEffect(() => {
    if (!_.isEmpty(pin)) {
      setValue("carRegistrationState", pin?.state?.state_name);
      setValue("carRegistrationStateId", pin?.state?.state_id);
    } else {
      setValue("carRegistrationState", "");
      setValue("carRegistrationStateId", "");
      setValue("carRegistrationCity", "");
      setValue("carRegistrationCityId", "");
    }
  }, [pin]);
};

export const useSetCarRegistrationCity = (
  city,
  pin,
  setValue,
  vehicle,
  CardData
) => {
  useEffect(() => {
    if (city) {
      const city_id = pin?.city?.filter(({ city_name }) => city_name === city);

      !_.isEmpty(city_id)
        ? setValue("carRegistrationCityId", city_id[0]?.city_id)
        : setValue(
            "carRegistrationCityId",
            vehicle?.carRegistrationCityId ||
              CardData?.vehicle?.carRegistrationCityId
          );
    }
  }, [city, pin]);
};

export const useHandleSearchFinancer = (temp_data, vehicle, handleSearch) => {
  const [initialExecution, setInitialExecution] = useState(false);
  useEffect(() => {
    if (
      (vehicle?.nameOfFinancer || temp_data?.userProposal?.nameOfFinancer) &&
      !initialExecution
    ) {
      handleSearch(
        vehicle?.nameOfFinancer || temp_data?.userProposal?.nameOfFinancer
      );
      setInitialExecution(true);
    }
  }, [temp_data]);
};

export const useSetFinancerDetails = (financer_sel, setValue, CardData) => {
  useEffect(() => {
    if (!_.isEmpty(financer_sel) && financer_sel) {
      if (financer_sel[0]?.code && financer_sel[0]?.name) {
        //Incase of a normal selection.
        setValue("nameOfFinancer", financer_sel[0]?.code);
        setValue("financer_name", financer_sel[0]?.name);
        setValue("fullNameFinance", financer_sel[0]?.name);
      }
      if (financer_sel[0]?.customOption) {
        //Incase of custon selection
        setValue("nameOfFinancer", financer_sel[0]?.name);
        setValue("financer_name", financer_sel[0]?.name);
        setValue("fullNameFinance", financer_sel[0]?.name);
      }
    } else {
      if (_.isEmpty(financer_sel) && CardData?.vehicle?.financerName) {
        setValue("financer_name", CardData?.vehicle?.financerName);
        setValue("fullNameFinance", CardData?.vehicle?.fullNameFinance);
      }
    }
  }, [financer_sel]);
};

export const useSetVehicleRegistrationNumber = (
  RegNo1,
  RegNo2,
  RegNo3,
  setValue,
  temp_data
) => {
  useEffect(() => {
    const regNo1Trimmed = RegNo1 ? RegNo1.replace(/\s/g, "") : "";
    const regNo2Trimmed = RegNo2 ? RegNo2.replace(/\s/g, "") : "";
    const regNo3Trimmed = RegNo3 ? RegNo3.replace(/\s/g, "") : "";

    const newValue =
      RegNo1 && RegNo2 && RegNo3
        ? `${regNo1Trimmed}-${regNo2Trimmed}-${regNo3Trimmed}`
        : RegNo1 && !RegNo2 && RegNo3
        ? `${regNo1Trimmed}-${regNo3Trimmed}`
        : RegNo1 &&
          !RegNo2 &&
          !RegNo3 &&
          temp_data?.corporateVehiclesQuoteRequest?.businessType ===
            "newbusiness"
        ? "NEW"
        : null;

    setValue("vehicaleRegistrationNumber", newValue);
  }, [RegNo1, RegNo2, RegNo3]);
};

export const useFastLanePrefill = (CardData, vehicle, temp_data, setValue) => {
  useEffect(() => {
    if (_.isEmpty(CardData?.vehicle)) {
      !vehicle?.chassisNumber &&
        temp_data?.userProposal?.chassisNumber &&
        setValue("chassisNumber", temp_data?.userProposal?.chassisNumber);
      !vehicle?.vehicleColor &&
        temp_data?.userProposal?.engineNumber &&
        setValue("engineNumber", temp_data?.userProposal?.engineNumber);
      !vehicle?.engineNumber &&
        temp_data?.userProposal?.vehicleColor &&
        setValue("vehicleColor", temp_data?.userProposal?.vehicleColor);
      !vehicle?.nameOfFinancer &&
        temp_data?.userProposal?.nameOfFinancer &&
        setValue("isVehicleFinance", true);
      !vehicle?.vehicleCategory &&
        temp_data?.userProposal?.vehicleCategory &&
        setValue("vehicleCategory", temp_data?.userProposal?.vehicleCategory);
      !vehicle?.vehicleCategory &&
        temp_data?.parent?.productSubTypeCode === "GCV" &&
        temp_data?.userProposal?.hazardousType &&
        setValue("hazardousType", temp_data?.userProposal?.hazardousType);
      !vehicle?.pucNo &&
        ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
          temp_data?.corporateVehiclesQuoteRequest?.businessType !==
            "newbusiness") ||
          temp_data?.selectedQuote?.companyAlias !== "tata_aig") &&
        temp_data?.userProposal?.pucNo &&
        setValue("pucNo", temp_data?.userProposal?.pucNo);
      !vehicle?.pucExpiry &&
        ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
          temp_data?.corporateVehiclesQuoteRequest?.businessType !==
            "newbusiness") ||
          temp_data?.selectedQuote?.companyAlias !== "tata_aig") &&
        temp_data?.userProposal?.pucExpiry &&
        setValue("pucExpiry", temp_data?.userProposal?.pucExpiry);
    }
  }, [CardData?.vehicle]);
};

export const useSetVehicleUsageType = (
  vehicleCategoryVar,
  CardData,
  vehicle,
  vehicleUsageTypeVar,
  temp_data,
  setValue
) => {
  useEffect(() => {
    if (
      vehicleCategoryVar &&
      _.isEmpty(CardData?.vehicle) &&
      !vehicle?.vehicleUsageType &&
      !vehicleUsageTypeVar &&
      temp_data?.userProposal?.vehicleUsageType
    ) {
      setValue("vehicleUsageType", temp_data?.userProposal?.vehicleUsageType);
    }
  }, [vehicleCategoryVar]);
};

export const useSetFinancerData = (
  isVehicleFinance,
  CardData,
  vehicle,
  temp_data,
  setValue
) => {
  useEffect(() => {
    if (isVehicleFinance && _.isEmpty(CardData?.vehicle)) {
      !vehicle?.nameOfFinancer &&
        temp_data?.userProposal?.nameOfFinancer &&
        setValue("nameOfFinancer", temp_data?.userProposal?.nameOfFinancer);
      !vehicle?.financerAgreementType &&
        temp_data?.userProposal?.financerAgreementType &&
        setValue(
          "financerAgreementType",
          temp_data?.userProposal?.financerAgreementType
        );
      !vehicle?.hypothecationCity &&
        temp_data?.userProposal?.hypothecationCity &&
        setValue(
          "hypothecationCity",
          temp_data?.userProposal?.hypothecationCity
        );
      !vehicle?.financerLocation &&
        temp_data?.userProposal?.financerLocation &&
        setValue("financerLocation", temp_data?.userProposal?.financerLocation);
    }
  }, [isVehicleFinance, CardData?.vehicle]);
};

export const useOnSuccessOngrid = (
  ongridLoad,
  type,
  temp_data,
  setValue,
  enquiry_id,
  token,
  _stToken,
  theme_conf,
  history
) => {
  useEffect(() => {
    if (
      ongridLoad &&
      !_.isEmpty(ongridLoad) &&
      ![101, 102, 103, 104].includes(ongridLoad?.status * 1) &&
      ongridLoad?.status &&
      !ongridLoad?.overrideMsg
    ) {
      // if (import.meta.env.VITE_BROKER === "OLA") {
      if (
        (ongridLoad?.ft_product_code &&
          ongridLoad?.ft_product_code !== TypeReturn(type)) ||
        ongridLoad?.sub_section
      ) {
        swal({
          text: `You've entered the registration number of a ${productType(
            ongridLoad?.sub_section || ongridLoad?.ft_product_code
          )}`,
          icon: "warning",
          buttons: {
            ...(temp_data?.corporateVehiclesQuoteRequest
              ?.journeyWithoutRegno === "Y" && {
              catch: {
                text: "Edit Reg. No.",
                value: "confirm",
              },
            }),
          },
          dangerMode: true,
          closeOnClickOutside: false,
        }).then((caseValue) => {
          switch (caseValue) {
            case "confirm":
              _haptics([100, 0, 50]);
              setValue("regNo2", "");
              setValue("regNo3", "");
              break;
            default:
          }
        });
      }
      // }
      const isBrokerAccepted = acceptedBrokers.includes(
        import.meta.env.VITE_BROKER
      );
      const isGridLoadMismatch =
        ongridLoad?.ft_product_code !== TypeReturn(type) ||
        ongridLoad?.sub_section;
      if (
        (isBrokerAccepted ||
          (import.meta.env.VITE_BROKER === "GRAM" &&
            TypeReturn(type) === "bike")) &&
        isGridLoadMismatch
      ) {
        swal({
          text: `You've entered the registration number of a ${productType(
            ongridLoad?.sub_section || ongridLoad?.ft_product_code
          )}`,
          icon: "warning",
          buttons: {
            ...(temp_data?.corporateVehiclesQuoteRequest
              ?.journeyWithoutRegno === "Y" && {
              catch: {
                text: "Edit Reg. No.",
                value: "confirm",
              },
            }),
            No: {
              text: `Proceed to ${
                ongridLoad?.sub_section || ongridLoad?.ft_product_code
              } journey`,
              value: "No",
            },
          },
          dangerMode: true,
          closeOnClickOutside: false,
        }).then((caseValue) => {
          switch (caseValue) {
            case "confirm":
              _haptics([100, 0, 50]);
              setValue("regNo2", "");
              setValue("regNo3", "");
              break;
            case "No":
              ongridLoad?.redirectionUrl &&
                reloadPage(
                  `${
                    ongridLoad?.redirectionUrl
                  }/registration?enquiry_id=${enquiry_id}${
                    token ? `&xutm=${token}` : ""
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
              break;
            default:
          }
        });
      }
    }
    if (
      ongridLoad &&
      !_.isEmpty(ongridLoad) &&
      [102, 103, 104, 110]?.includes(ongridLoad?.status * 1) &&
      ongridLoad?.status * 1 !== 101
    ) {
      [103, 104]?.includes(ongridLoad?.status * 1)
        ? swal(
            "Please note",
            ongridLoad?.status * 1 === 103
              ? "This case belongs to another RenewBuy agent"
              : "This Rc Number Blocked On Portal",
            "error"
          ).then(() =>
            history.replace(
              `/${type}/registration?enquiry_id=${enquiry_id}${
                token ? `&xutm=${token}` : ``
              }${_stToken ? `&stToken=${_stToken}` : ``}`
            )
          )
        : swal({
            title: "Please note",
            text: `${
              ongridLoad?.overrideMsg
                ? ongridLoad?.overrideMsg
                : theme_conf?.broker_config?.vahan_error &&
                  theme_conf?.broker_config?.vahan_error
                ? theme_conf?.broker_config?.vahan_error
                : "Unable to fetch vehicle class details , please coordinate with your respective RM to proceed ahead."
            } `,
            icon: "info",
            buttons: {
              cancel: "Okay",
              catch: {
                text: "Retry",
                value: "confirm",
              },
            },
            dangerMode: true,
          }).then((caseValue) => {
            switch (caseValue) {
              case "confirm":
                setValue("regNo2", "");
                setValue("regNo3", "");
                break;
              default:
                history.replace(
                  `/${type}/registration?enquiry_id=${enquiry_id}${
                    token ? `&xutm=${token}` : ``
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
            }
          });
    }
  }, [ongridLoad]);
};

export const useFocusOnCategory = (category, vehicle, CardData) => {
  const [oneClick, setnewClick] = useState(0);
  useEffect(() => {
    if (
      _.isEmpty(CardData?.vehicle) &&
      _.isEmpty(vehicle) &&
      !_.isEmpty(category) &&
      !oneClick
    ) {
      if (document.getElementById("vehicleCategory")) {
        document.getElementById("vehicleCategory").focus();
        document.getElementById("vehicleCategory").blur();
      }
      setnewClick(1);
    }
  }, [category]);
};

export const useFetchColorMaster = (dispatch, temp_data) => {
  useEffect(() => {
    if (icWithColorMaster.includes(temp_data?.selectedQuote?.companyAlias)) {
      dispatch(getColor(temp_data?.selectedQuote?.companyAlias));
    }
  }, []);
};
