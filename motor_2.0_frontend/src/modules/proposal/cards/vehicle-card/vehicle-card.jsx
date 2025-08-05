import React, { useState, useEffect, useMemo } from "react";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm, Controller } from "react-hook-form";
import _ from "lodash";
import { fetchToken, _haptics } from "utils";
import { useDispatch, useSelector } from "react-redux";
import { useHistory } from "react-router";
import {
  getAgreement,
  getFinancer,
  Category,
  Usage,
  AdrilaLoad,
} from "../../proposal.slice";
import styled from "styled-components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import {
  vehicleValidation,
  ChassisValidation,
  EngineValidation,
} from "../../form-section/validation";
// prettier-ignore
import { useBranchMaster, useFastLanePrefill, useFetchIcBaseValidation,
         useFetchPinCode, useFocusOnCategory, useHandleSearchFinancer, 
         useOnSuccessOngrid, usePrefillApi, useSetFinancerData, 
         useSetCarRegistrationCity, useSetFinancerDetails, useSetPinCodeAndState,
         useSetVehicleRegistrationNumber, useSetVehicleUsageType, useFetchColorMaster
        } from "./vehicle-card-hooks";
import {
  _dateConfig,
  getDefaultVehicleValues,
} from "./helper";
import {
  ongridConditions,
  ongridReadOnly,
} from "modules/proposal/form-section/proposal-logic";
import { CardForm } from "./vehicle-card-form";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const VehicleCard = ({
  onSubmitVehicle,
  vehicle,
  CardData,
  Theme,
  type,
  lessthan768,
  lessthan376,
  fields,
  PolicyCon,
  TypeReturn,
  enquiry_id,
  token,
  isEditable,
  zd_rti_condition
}) => {
  //props grouping
  const history = useHistory();

  const {
    temp_data,
    carPincode: pin,
    financer: financerList,
    agreement,
    category,
    usage,
    ongridLoad,
    gridLoad,
    colors,
    branchMaster,
  } = useSelector((state) => state.proposal);
  const _stToken = fetchToken();

  /*---------------date config----------------*/
  const { ManfVal, ManfValMax } = _dateConfig(temp_data);
  /*-----x---------date config-----x----------*/

  const [addValidation, setAddValidation] = useState(false);
  const [financeValidation, setFinanceValidation] = useState(false);
  const [validations, setValidations] = useState(null);
  const [pucVal, setPucVal] = useState("");
  const dispatch = useDispatch();

  const { validationConfig: validation, theme_conf, vahaanConfig } = useSelector(
    (state) => state.home
  );

  //fetching ic based validations
  useFetchIcBaseValidation(validation, temp_data, setValidations);

  //chassis validation
  const chasisVal = { ...ChassisValidation(validations, temp_data) };

  //engine validation
  const engineVal = { ...EngineValidation(validations) };

  // engine number regx
  const engineRegx = validations?.regxengine;
  const enginePattern = engineRegx ? new RegExp(engineRegx) : /[sS]*/;

  // chassis number regx
  const chassisRegx = validations?.regxchassis;
  const chassisPattern = chassisRegx ? new RegExp(chassisRegx) : /[sS]*/;
 
  /*----------------Validation Schema---------------------*/
  let _typeReturn = TypeReturn(type)
  //prettier-ignore
  const yupValidate = yup.object({
    ...vehicleValidation(
      temp_data, addValidation, financeValidation, pucVal, _typeReturn,
      enginePattern, validations, engineVal, chassisPattern, chasisVal, fields
    ),
  });
  /*----------x------Validation Schema----------x-----------*/
  const regNo = temp_data?.regNo ? temp_data?.regNo : "";
  const regNo2 = temp_data?.regNo1 ? temp_data?.regNo2 : "";
  const regNo3 = temp_data?.regNo1 ? temp_data?.regNo3 : "";

  const { handleSubmit, register, errors, control, reset, setValue, watch } =
    // eslint-disable-next-line react-hooks/rules-of-hooks
    useForm({
      defaultValues: {
        ...getDefaultVehicleValues(temp_data, vehicle, regNo, CardData),
        financer_sel:
          vehicle?.financerSel || temp_data?.userProposal?.financerSel,
      },
      resolver: yupResolver(yupValidate),
      mode: "onBlur",
      reValidateMode: "onBlur",
    });

  const allFieldsReadOnly =
    temp_data?.selectedQuote?.isRenewal === "Y" && !isEditable;

  const PUC_NO = watch("pucNo");
  const PUC_EXPIRY = watch("pucExpiry");
  const pucMandatory =
    (fields.includes("pucExpiry") &&
      //PUC is not mandatory in new business
      temp_data?.corporateVehiclesQuoteRequest?.businessType !==
        "newbusiness" &&
      temp_data?.selectedQuote?.companyAlias !== "tata_aig") ||
    //PUC is mandatory for DL in case of TATA AIG but not in new business.
    (fields.includes("pucExpiry") &&
      (((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
        temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
        temp_data?.corporateVehiclesQuoteRequest?.businessType !==
          "newbusiness") || temp_data?.selectedQuote?.companyAlias !== "tata_aig") ||
        //If either of the number or date is entered then both the fields will be mandatory
        pucVal));

  useEffect(() => {
    if (PUC_NO || PUC_EXPIRY) {
      setPucVal(true);
    } else {
      setPucVal(false);
    }
  }, [PUC_NO, PUC_EXPIRY]);

  //prefill Api
  // prettier-ignore
  usePrefillApi(vehicle, CardData, reset, temp_data, regNo, setFinanceValidation, setAddValidation);

  //setting validations
  const isVehicleFinance = watch("isVehicleFinance");
  const isCarRegistrationAddressSame = watch("isCarRegistrationAddressSame");

  useEffect(() => {
    if (isVehicleFinance) {
      setFinanceValidation(true);
    } else {
      setFinanceValidation(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isVehicleFinance]);

  useEffect(() => {
    if (!isCarRegistrationAddressSame) {
      setAddValidation(true);
    } else {
      setAddValidation(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isCarRegistrationAddressSame]);

  //pincode
  const companyAlias =
    !_.isEmpty(temp_data?.selectedQuote) &&
    temp_data?.selectedQuote?.companyAlias;

  const PinCode = watch("carRegistrationPincode");

  // fetch pincode
  useFetchPinCode(PinCode, dispatch, companyAlias, enquiry_id);

  // set pinCode in state
  useSetPinCodeAndState(pin, setValue);

  // auto selecting if only one option is present.
  const city =
    watch("carRegistrationCity") ||
    vehicle?.carRegistrationCity ||
    CardData?.vehicle?.carRegistrationCity ||
    (!_.isEmpty(pin?.city) &&
      pin?.city?.length === 1 &&
      pin?.city[0]?.city_name);

  // set car registration city
  useSetCarRegistrationCity(city, pin, setValue, vehicle, CardData);

  //vehicle details
  const manfYear = vehicle?.vehicleManfYear
    ? vehicle?.vehicleManfYear
    : temp_data?.manfDate;

  const RegDate = temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate;

  useEffect(() => {
    if (manfYear) setValue("vehicleManfYear", manfYear);
    if (RegDate) setValue("registrationDate", RegDate);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [manfYear, RegDate, vehicle?.vehicleManfYear]);

  //setting hidden i/p
  const financer_sel = watch("financer_sel");

  //Financer
  const handleSearch = (query) => {
    if (companyAlias && query?.length >= 3) {
      dispatch(
        getFinancer({
          companyAlias,
          searchString: query,
          enquiryId: enquiry_id,
        })
      );
    }
  };

  // handle search name of financer
  useHandleSearchFinancer(temp_data, vehicle, handleSearch);

  // set financer details
  useSetFinancerDetails(financer_sel, setValue, CardData);

  //financer_sel
  const filteredFinancer =
    !_.isEmpty(financerList) &&
    _.uniqBy(
      financerList?.filter((i) =>
        vehicle?.financerSel
          ? i?.name === vehicle?.fullNameFinance
          : i?.name === temp_data?.userProposal?.fullNameFinance
      ),
      "name"
    );
  const financer_sel_opt = vehicle?.financerSel || filteredFinancer;
  const FinancerInputValue =
    vehicle?.fullNameFinance ||
    vehicle?.nameOfFinancer ||
    (!vehicle?.fullNameFinance && temp_data?.userProposal?.fullNameFinance);

  useEffect(() => {
    if (!_.isEmpty(filteredFinancer) && !financer_sel) {
      setValue("financer_sel", filteredFinancer);
    }
  }, [filteredFinancer]);

  useEffect(() => {
    if (companyAlias && isVehicleFinance) {
      dispatch(getAgreement({ companyAlias, enquiryId: enquiry_id }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyAlias, isVehicleFinance]);

  //Fetch Color master for applicable ICs
  useFetchColorMaster(dispatch, temp_data);

  const Agreement = !_.isEmpty(agreement)
    ? agreement?.map(({ name, code }) => {
        return { name, code };
      })
    : [];

  const AgreementType = watch("financerAgreementType");
  const AgreementTypeName =
    !_.isEmpty(agreement) &&
    agreement?.filter(({ code }) => code === AgreementType)[0]?.name;
  const VehicleRegNo = watch("regNo");
  //Reconstructing Vehicle Reg Number (General), This will not be executed for a BH reg no.
  const RegNo1 = watch("regNo1");
  const RegNo2 = watch("regNo2");
  const RegNo3 = watch("regNo3");

  // set vehicle registration number
  useSetVehicleRegistrationNumber(RegNo1, RegNo2, RegNo3, setValue, temp_data);

  //Vehicle Categories
  useEffect(() => {
    dispatch(Category());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const vehicleCategoryVar = watch("vehicleCategory");
  const vehicleUsageTypeVar = watch("vehicleUsageType");
  //vehicle Usage Type
  useEffect(() => {
    if (vehicleCategoryVar) dispatch(Usage(vehicleCategoryVar));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vehicleCategoryVar]);

  //fastLane prefill
  useFastLanePrefill(CardData, vehicle, temp_data, setValue);

  // set vehicle Usage Type
  // prettier-ignore
  useSetVehicleUsageType(vehicleCategoryVar, CardData, vehicle, vehicleUsageTypeVar, temp_data, setValue);

  // set name of financer, financer agreement type, hypothecation City and financer Location
  useSetFinancerData(isVehicleFinance, CardData, vehicle, temp_data, setValue);

  //puc deails
  const PUC_EXP = watch("pucExpiry");

  //ongridload
  const OnGridLoad = () => {
    if (
      ((RegNo1 &&
        RegNo1.match(/^[A-Z]{2}[-\s][0-9]{1,2}$/) &&
        RegNo2 &&
        RegNo2.match(/^[A-Z\s]{1,3}$/) &&
        RegNo3 &&
        RegNo3.match(/^[0-9]{4}$/)) ||
        (VehicleRegNo && VehicleRegNo[0] * 1)) &&
      ongridConditions(vahaanConfig, TypeReturn(type))
    ) {
      dispatch(
        AdrilaLoad(
          {
            registration_no:
              VehicleRegNo && VehicleRegNo[0] * 1
                ? VehicleRegNo
                : `${RegNo1}-${RegNo2}-${RegNo3}`,
            enquiryId: enquiry_id,
            section: TypeReturn(type),
            vehicleValidation: "Y",
          },
          true
        )
      );
    } else if (
      !RegNo2 &&
      RegNo1 &&
      RegNo1.match(/^[A-Z]{2}[-\s][0-9]{1,2}$/) &&
      RegNo3 &&
      RegNo3.match(/^[0-9]{4}$/) &&
      ongridConditions(vahaanConfig, TypeReturn(type))
    ) {
      dispatch(
        AdrilaLoad(
          {
            registration_no: `${RegNo1}-${RegNo3}`,
            enquiryId: enquiry_id,
            section: TypeReturn(type),
            resetDetails: true,
            vehicleValidation: "Y",
          },
          true
        )
      );
    }
  };

  //onGridLoad if reg no is already populated and readOnly
  useMemo(() => {
    if (ongridReadOnly(TypeReturn(type), temp_data)) {
      OnGridLoad();
    }
  }, [
    temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
    VehicleRegNo,
    RegNo3,
  ]);

  //onSuccess
  // prettier-ignore
  useOnSuccessOngrid( ongridLoad, type, temp_data, setValue, enquiry_id, token, _stToken, theme_conf, history);

  //for selecting cascade dropdowns
  useFocusOnCategory(category, vehicle, CardData);

  //check for IDV inputs
  const idvChange = watch("chassisIdv") || watch("bodyIdv");

  //Fetch branch master
  const financerCode = watch("nameOfFinancer");
  useBranchMaster(dispatch, temp_data, branchMaster, financerCode);

  //Prop grouping
  const hooks = { handleSubmit, register, errors, control, watch, Controller, setValue };
  //Functions
  const propFunctions = { OnGridLoad, onSubmitVehicle, handleSearch };
  //media queries
  const mediaQueries = { lessthan376, lessthan768 };
  //states
  const propStates = { temp_data, CardData, vehicle };
  //List & Options
  const lists = {
    category,
    usage,
    colors,
    financerList,
    Agreement,
    branchMaster,
  };
  //conditions
  //prettier-ignore
  const conditions = { allFieldsReadOnly, pucMandatory, PUC_EXP, idvChange,
    fields, PolicyCon, gridLoad, zd_rti_condition
   }
  //variables
  //prettier-ignore
  const propVariables = { regNo, regNo2, regNo3, VehicleRegNo, RegNo1,
          RegNo2, RegNo3, ManfVal, ManfValMax, type, Theme,
          financer_sel_opt, FinancerInputValue, AgreementTypeName,
          companyAlias, pin
        }

  const allProps = {
    ...hooks,
    ...propFunctions,
    ...mediaQueries,
    ...propStates,
    ...lists,
    ...conditions,
    ...propVariables,
  };

  return <CardForm allProps={allProps} />;
};

export const TopDiv = styled.div`
  .switch input:checked + .slider {
    background-color: ${({ theme }) =>
      theme.questionsProposal?.toggleBackgroundColor || "#006600"};
  }
`;

export const StyledDatePicker = styled.div`
  .dateTimeOne .date-header {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
  .dateTimeOne {
    ${(props) => (props?.disabled ? `cursor: not-allowed !important;` : ``)}
  }
  .dateTimeOne .react-datepicker__input-container input {
    ${(props) => (props?.disabled ? `cursor: not-allowed !important;` : ``)}
  }
  .dateTimeOne .react-datepicker__day:hover {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
`;

export default VehicleCard;
