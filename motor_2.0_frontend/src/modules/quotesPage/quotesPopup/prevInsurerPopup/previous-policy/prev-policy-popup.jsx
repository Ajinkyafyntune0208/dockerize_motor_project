import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { setTempData } from "../../../filterConatiner/quoteFilter.slice";
import { useForm } from "react-hook-form";
import { getNewNcb, getCalculatedNcb } from "components";
import Popup from "components/Popup/Popup";
import { set_temp_data } from "modules/Home/home.slice";
import { useDispatch, useSelector } from "react-redux";
import moment from "moment";
import { toDate } from "utils";
import { addDays, subDays, differenceInDays, addYears } from "date-fns";
import "../preInsurerPopup.scss";
import { differenceInYears } from "date-fns/esm";
import { useMediaPredicate } from "react-media-hook";
import "react-dates/initialize";
import "react-dates/lib/css/_datepicker.css";
import { CancelAll, clear } from "modules/quotesPage/quote.slice";
import { GlobalStyle } from "./style/style";
import PreviousPolicyDrawer from "./drawer/PrevPolicyDrawer";
import PreviousPolicyContent from "./contents/prev-policy-content";
import {
  useMobileDrawer,
  usePolicyTypeSelected,
  usePrevIcVisibility,
  useRenewalAndOdOnly,
  useStepBasedOnOd,
} from "./prev-ins-hooks/prev-ins-hooks";
import {
  calculatePolicyMaxDate,
  calculatePolicyMinDate,
  calculatePolicyMinDate1,
  dateValidation,
  getTpDate,
} from "./helper";

const PrevPolicyPopup = ({
  show,
  onClose,
  edit,
  setEdit,
  type,
  ConfigNcb,
  showPrevPopUp,
  assistedMode,
}) => {
  const { handleSubmit, register, control, errors } = useForm();
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { temp_data } = useSelector((state) => state.home);
  const { tempData } = useSelector((state) => state.quoteFilter);
  //handle policy type
  const [prevPolicyType, setPrevPolicyType] = useState(tempData?.policyType);

  //calculating renewal margin and OD Only
  const { renewalMargin, odOnly, setOdOnly } = useRenewalAndOdOnly(
    temp_data,
    type
  );
  //policy type selected logic
  const policyTypeSelected = usePolicyTypeSelected(tempData?.policyType);
  //setting step based on od
  const { step, setStep } = useStepBasedOnOd(
    odOnly,
    renewalMargin,
    policyTypeSelected,
    temp_data
  );

  /*---------------date config----------------*/
  const policyMin = calculatePolicyMinDate(temp_data?.regDate);
  // prettier-ignore
  const policyMin1 = calculatePolicyMinDate1( temp_data, renewalMargin, odOnly, tempData, type);
  // prettier-ignore
  const policyMax = calculatePolicyMaxDate( temp_data, renewalMargin, odOnly, tempData, type);

  /*-----x---------date config-----x----------*/
  // page 2 logic

  const dispatch = useDispatch();

  //setting visible month on datepicker
  const prevIcData = usePrevIcVisibility(temp_data?.prevIc);

  let today = moment().format("DD-MM-YYYY");
  let regDate = temp_data?.regDate;
  let diffYear =
    regDate && today && differenceInYears(toDate(today), toDate(regDate));

  const calculatedNcb = getCalculatedNcb(diffYear)
     
  //If expiry date is selected
  const onSubmit = async (date) => {
    !assistedMode && dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    let a = date;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    let regDateFormat = regDate.slice(6);
    // convert string into date
    let dateWithoutFormat = moment(date, "DD-MM-YYYY").toDate();
    // increase date by 1 day
    let result = new Date(dateWithoutFormat);
    result.setDate(result.getDate() + 1);
    // convert date into string
    let finalResult = moment(result).format("DD-MM-YYYY");
    let selectedExpiryDate = finalResult.slice(0, 6);

    if (
      //display prev popup when config is true
      ConfigNcb &&
      //display prev popup if expiry date is not more than 90 days past
      !(diffDays && diffDays > 90) &&
      //display prev popup if it's a non new business, if prev IC is absent and prevPolicy type is not tp.
      !temp_data?.newCar &&
      !prevIcData &&
      prevPolicyType !== "Third-party"
    ) {
      // invoking function to display prevIC pop up
      showPrevPopUp(
        {
          expiry: date,
          policyExpired: diffDays && diffDays > 0 ? true : false,
          leadJourneyEnd: true,
          breakIn: diffDays && diffDays > 0 ? true : false,
          ...(prevPolicyType === "Third-party" &&
            temp_data?.regDate &&
            !temp_data?.expiry &&
            ((temp_data?.regDate.split("-")[2] * 1 ===
              new Date().getFullYear() * 1 &&
              new Date().getMonth() + 1 * 1 < 10) ||
              temp_data?.regDate.split("-")[2] * 1 !==
                new Date().getFullYear() * 1) && {
              regDate: selectedExpiryDate + regDateFormat,
            }),
        },
        {
          policyType: prevPolicyType ? prevPolicyType : "Comprehensive",
        }
      );
    } else {
      dispatch(
        set_temp_data({
          ncb:
            prevPolicyType === "Third-party"
              ? "0%"
              : diffDays && diffDays > 90
              ? "0%"
              : temp_data?.ncb &&
                (temp_data?.isNcbVerified === "Y" ||
                  temp_data?.prevShortTerm * 1)
              ? temp_data?.ncb
              : diffYear > 8
              ? "50%"
              : calculatedNcb,
          expiry: date,
          noClaimMade: temp_data?.noClaimMade ? temp_data?.noClaimMade : false,
          policyExpired: diffDays && diffDays > 0 ? true : false,
          newNcb:
            prevPolicyType === "Third-party"
              ? "0%"
              : diffDays && diffDays > 90
              ? "0%"
              : temp_data?.newNcb &&
                (temp_data?.isNcbVerified === "Y" ||
                  temp_data?.prevShortTerm * 1)
              ? temp_data?.newNcb
              : getNewNcb(diffYear > 8 ? "50%" : calculatedNcb),
          leadJourneyEnd: true,
          ...(diffDays && diffDays > 90 && {isNcbVerified: "N"}),
          carOwnership: temp_data?.carOwnership
            ? temp_data?.carOwnership
            : false,
          breakIn: diffDays && diffDays > 0 ? true : false,
          ...(prevPolicyType === "Third-party" &&
            temp_data?.regDate &&
            !temp_data?.expiry &&
            ((temp_data?.regDate.split("-")[2] * 1 ===
              new Date().getFullYear() * 1 &&
              new Date().getMonth() + 1 * 1 < 10) ||
              temp_data?.regDate.split("-")[2] * 1 !==
                new Date().getFullYear() * 1) && {
              regDate: selectedExpiryDate + regDateFormat,
            }),
        })
      );

      dispatch(
        setTempData({
          policyType: prevPolicyType ? prevPolicyType : "Comprehensive",
        })
      );
      !assistedMode && dispatch(CancelAll(false));
      dispatch(clear());
      onClose(false);
    }
  };
  //Onsubmit if a Direct MultiYear TP is selected as prev policy type
  //If expiry date is selected
  const onSubmitTP = async (date, isMultiTP_OD) => {
    !assistedMode && dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    let a = date;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    if (
      //display prev popup when config is true
      ConfigNcb &&
      //display prev popup if expiry date is not more than 90 days past
      !(diffDays && diffDays > 90) &&
      //display prev popup if it's a non new business, if prev IC is absent and prevPolicy type is not tp.
      !temp_data?.newCar &&
      !prevIcData &&
      isMultiTP_OD
    ) {
      // invoking function to display prevIC pop up
      showPrevPopUp(
        {
          expiry: date,
          policyExpired: diffDays && diffDays > 0 ? true : false,
          leadJourneyEnd: true,
          breakIn: diffDays && diffDays > 0 ? true : false,
        },
        {
          policyType: !isMultiTP_OD ? "Third-party" : "Comprehensive",
        }
      );
    } else {
      dispatch(
        set_temp_data({
          ncb:
            prevPolicyType === "Third-party" || !isMultiTP_OD
              ? "0%"
              : diffDays && diffDays > 90
              ? "0%"
              : temp_data?.ncb &&
                (temp_data?.isNcbVerified === "Y" ||
                  temp_data?.prevShortTerm * 1)
              ? temp_data?.ncb
              : diffYear > 8
              ? "50%"
              : getCalculatedNcb(diffYear),
          expiry: date,
          noClaimMade: temp_data?.noClaimMade ? temp_data?.noClaimMade : true,
          policyExpired: diffDays && diffDays > 0 ? true : false,
          newNcb:
            prevPolicyType === "Third-party" || !isMultiTP_OD
              ? "0%"
              : diffDays && diffDays > 90
              ? "0%"
              : temp_data?.newNcb &&
                (temp_data?.isNcbVerified === "Y" ||
                  temp_data?.prevShortTerm * 1)
              ? temp_data?.newNcb
              : getNewNcb(diffYear > 8 ? "50%" : calculatedNcb),
          leadJourneyEnd: true,
          carOwnership: temp_data?.carOwnership
            ? temp_data?.carOwnership
            : false,
          breakIn: diffDays && diffDays > 0 ? true : false,
          isExpiryModified: "Yes",
        })
      );

      dispatch(
        setTempData({
          policyType: !isMultiTP_OD ? "Third-party" : "Comprehensive",
        })
      );
      !assistedMode && dispatch(CancelAll(false));
      dispatch(clear());
      onClose(false);
    }
  };

  setTimeout(() => {
    if (temp_data?.expiry) {
      setEdit(false);
    }
  }, 500);

  const handleNoPrev = () => {
    dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    setOdOnly(false);
    dispatch(
      set_temp_data({
        odOnly: false,
        ncb: "0%",
        expiry: "New",
        policyExpired: false,
        newNcb: "0%",
        prevIc: "New",
        prevIcFullName: "New",
        noClaimMade: true,
        leadJourneyEnd: true,
        carOwnership: false,
        breakIn: true,
        isNcbConfirmed: true,
        policyType: "Not sure",
        previousPolicyTypeIdentifier: "N",
        isPopupShown: "N",
      })
    );
    dispatch(
      setTempData({
        odOnly: false,
        policyType: "Not sure",
        previousPolicyTypeIdentifier: "N",
        isPopupShown: "N",
      })
    );
    dispatch(CancelAll(false));
    dispatch(clear());
    onClose(false);
  };

  const singleYearCase = () => {
    setOdOnly(false);
    dispatch(
      set_temp_data({
        odOnly: false,
        previousPolicyTypeIdentifier: "Y",
      })
    );
    dispatch(
      setTempData({
        odOnly: false,
        previousPolicyTypeIdentifier: "Y",
      })
    );
  };

  const [date, setDate] = useState(false);
  const [isFocused, setIsFocused] = useState(true);

  function onDateChange(date) {
    setDate(date);
    let newDate = moment(date).toDate().toISOString();

    let newDateFormated = moment(newDate).format("DD-MM-YYYY");

    onSubmit(newDateFormated);

    dispatch(
      setTempData({
        unFormatedExpityDate: date,
      })
    );
  }

  function onFocusChange({ focused }) {
    setIsFocused(true);
  }

  //setting initial step for od
  //cancel all logic
  useEffect(() => {
    if (!temp_data?.expiry) {
      dispatch(CancelAll(true));
    }
  }, [temp_data?.expiry]);

  const handlePrevPolicySelection = (data, singleYear, is_bundled) => {
    if (data === "Comprehensive") {
      setPrevPolicyType("Comprehensive");
      singleYear && singleYearCase(); // OD false
      is_bundled &&
        onSubmitTP(
          (temp_data?.vehicleInvoiceDate || temp_data?.regDate) &&
            moment(
              subDays(
                addYears(
                  new Date(
                    `${(temp_data?.vehicleInvoiceDate || temp_data?.regDate)?.split("-")[2]}`,
                    `${(temp_data?.vehicleInvoiceDate || temp_data?.regDate)?.split("-")[1] * 1 - 1}`,
                    `${(temp_data?.vehicleInvoiceDate || temp_data?.regDate)?.split("-")[0]}`
                  ),
                  1
                ),
                1
              )
            ).format("DD-MM-YYYY"),
          is_bundled
        );
    } else if (data === "Third-party") {
      setPrevPolicyType("Third-party");
      singleYear && singleYearCase(); // OD false
      !singleYear &&
        !renewalMargin &&
        odOnly &&
        onSubmitTP((temp_data?.vehicleInvoiceDate || temp_data?.regDate) && getTpDate((temp_data?.vehicleInvoiceDate || temp_data?.regDate), type));
    } else if (data === "Not sure") {
      setPrevPolicyType("Not sure");
      handleNoPrev();
    } else if (data === "Own-damage") {
      setPrevPolicyType("Own-damage");
    }
    setStep(2);
    setTimeout(() => {
      onFocusChange({ focused: true });
    }, 1000);
  };

  //---drawer for mobile
  const { drawer, setDrawer } = useMobileDrawer(lessthan767, show);

  const content = (
    <PreviousPolicyContent
      step={step}
      handleSubmit={handleSubmit}
      onSubmit={onSubmit}
      temp_data={temp_data}
      odOnly={odOnly}
      renewalMargin={renewalMargin}
      tempData={tempData}
      lessthan767={lessthan767}
      control={control}
      policyMax={policyMax}
      policyMin={policyMin}
      type={type}
      policyMin1={policyMin1}
      isFocused={isFocused}
      onDateChange={onDateChange}
      onFocusChange={onFocusChange}
      errors={errors}
      register={register}
      handleNoPrev={handleNoPrev}
      handlePrevPolicySelection={handlePrevPolicySelection}
    />
  );

  return tempData?.policyType !== "Not sure" && !lessthan767 ? (
    <Popup
      height={"auto"}
      width="690px"
      top="40%"
      show={show}
      onClose={onClose}
      content={content}
      position="middle"
      outside={temp_data?.expiry ? false : true}
      overFlowDisable={true}
      hiddenClose={temp_data?.expiry ? false : true}
    />
  ) : (
    <>
      <PreviousPolicyDrawer
        drawer={drawer}
        setDrawer={setDrawer}
        onClose={onClose}
        temp_data={temp_data}
        content={content}
      />
      <GlobalStyle disabledBackdrop={temp_data?.expiry ? false : true} />
    </>
  );
};

// PropTypes
PrevPolicyPopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
PrevPolicyPopup.defaultProps = {
  show: false,
  onClose: () => {},
};

export default PrevPolicyPopup;
