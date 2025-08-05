import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { useForm, Controller } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { useDispatch, useSelector } from "react-redux";
import _ from "lodash";
import * as yup from "yup";
import moment from "moment";
import * as DateFns from "date-fns";
import { useMediaPredicate } from "react-media-hook";
import Drawer from "@mui/material/Drawer";
import { MultiSelect, ErrorMsg, getCalculatedNcb, getNewNcb } from "components";
import Popup from "components/Popup/Popup";
import DateInput from "modules//Home/steps/car-details/DateInput";
import "../policyTypePopup/policyTypePopup.css";
import { toDate, toDateOld } from "utils";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import * as restStyle from "./styles";
import { ownerType } from "./helper";
import { EditDetailsTop } from "./MMVDetails/editDetailsTop";
import { MMVSelection } from "./MMVDetails/MMVSelection";
import { setTempData } from "../../filterConatiner/quoteFilter.slice";
import { set_temp_data } from "modules/Home/home.slice";
import { CancelAll } from "modules/quotesPage/quote.slice";

// prettier-ignore
const { GlobalStyle, MobileDrawerBody, CloseButton,
        ContentWrap, HeaderPopup, ContentBox, UpdateButton,
        PremChangeWarning
      } = restStyle;

// prettier-ignore
const { addDays, subMonths, differenceInMonths, differenceInDays,
        getYear, differenceInYears, subDays, addYears
      } = DateFns;

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const yupValidate = yup.object({
  date2: yup.string().required("Date is required"),
  vehicleInvoiceDate : yup.string().required("Invoice Date is required"),
});

const EditInfoPopup = ({ show, onClose, type, TypeReturn, isEditable }) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const { register, handleSubmit, errors, setValue, watch, control } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onChange",
    reValidateMode: "onChange",
  });
  const PrevIcSelected = watch("preIc");
  const newRegDate = watch("date1");
  const newManDate = watch("date2");
  const InvoiceDate = watch("vehicleInvoiceDate");
  const { tempData, prevInsList } = useSelector((state) => state.quoteFilter);
  const { temp_data } = useSelector((state) => state.home);

  const [manufactureDate, setManfactureDate] = useState(false);

  //----------------setting maximum date for new registration date-----------------

  let policyMax = temp_data?.newCar
    ? addDays(new Date(Date.now()), 15)
    : subDays(subMonths(new Date(Date.now()), 9), 1);
  let policyMin = temp_data?.newCar && new Date(Date.now());
  //---------------setLoader for fuel api & clear previous fuel type data-------------------

  //-------------------------------Static OD----------------------------
  let c = "01-09-2018";
  let d = temp_data?.vehicleInvoiceDate || temp_data?.regDate;
  let e = moment().format("DD-MM-YYYY");
  let diffMonthsOd = d && c && differenceInMonths(toDate(d), toDate(c));
  let diffDaysOd = d && c && differenceInDays(toDate(d), toDate(c));
  let diffMonthsOdCar = d && e && differenceInMonths(toDate(e), toDate(d));
  let diffDayOd = d && e && differenceInDays(toDate(e), toDate(d));
  //calc days for edge cases in last month of renewal
  let diffDaysOdCar = e && d && differenceInDays(toDate(e), toDate(d));
  //OD time period used for static policy type display
  const staticOd =
    (diffDaysOd >= 0 &&
      diffDayOd > 270 &&
      (diffMonthsOdCar < 58 ||
        (diffMonthsOdCar === 58 && diffDaysOdCar <= 1095)) &&
      type === "bike") ||
    ((diffMonthsOdCar < 36 ||
      (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
      type === "car");
  const CheckForOD = (newInputReg) => {
    let c = "01-09-2018";
    let d = newInputReg;
    let e = moment().format("DD-MM-YYYY");
    let diffMonthsOd = d && c && differenceInMonths(toDate(d), toDate(c));
    let diffDaysOd = d && c && differenceInDays(toDate(d), toDate(c));
    let diffMonthsOdCar = d && e && differenceInMonths(toDate(e), toDate(d));
    let diffDayOd = d && e && differenceInDays(toDate(e), toDate(d));
    //calc days for edge cases in last month of renewal
    let diffDaysOdCar = e && d && differenceInDays(toDate(e), toDate(d));

    return (
      (diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        (diffMonthsOdCar < 58 ||
          (diffMonthsOdCar === 58 && diffDaysOdCar <= 1095)) &&
        type === "bike") ||
      ((diffMonthsOdCar < 36 ||
        (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
        type === "car" &&
        true)
    );
  };
  //----------------------------Static OD--------------------------------

  useEffect(() => {
    if (newManDate) {
      setManfactureDate(`01-${newManDate}`);
    }
  }, [newManDate]);

  const dispatch = useDispatch();

  //---------------------on submit function for edit info popup---------------------------------

  const onSubmit = () => {
    dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
    let today = moment().format("DD-MM-YYYY");
    let a = temp_data?.expiry;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    let diffYear =
      (InvoiceDate ? InvoiceDate : newRegDate) &&
      today &&
      differenceInYears(
        toDate(today),
        toDate(InvoiceDate ? InvoiceDate : newRegDate)
      );
    //calculating changes in policy type due to renewal margin reg date.
    //renewal margin
    let c = "01-09-2018";
    let d = InvoiceDate ? InvoiceDate : newRegDate;
    let diffMonthsOdCar = d && b && differenceInMonths(toDate(b), toDate(d));
    let diffDayOd = d && b && differenceInDays(toDate(b), toDate(d));
    //calc days for edge cases in last month of renewal
    let diffDaysOdCar = b && d && differenceInDays(toDate(b), toDate(d));
    
    dispatch(
      set_temp_data({
        regDate: newRegDate,
        vehicleInvoiceDate: InvoiceDate,
        manfDate: newManDate,
        prevIc: prevInsList.filter(
          (i) => i.previousInsurer === PrevIcSelected?.name
        )[0]?.companyAlias,
        prevIcFullName: PrevIcSelected?.name,
        ncb:
          tempData?.policyType === "Third-party" ||
          temp_data?.newCar ||
          tempData?.policyType === "Not sure"
            ? "0%"
            : diffDays && diffDays > 90
            ? "0%"
            : temp_data?.ncb && temp_data?.isNcbVerified === "Y"
            ? temp_data?.ncb
            : diffYear > 8
            ? "50%"
            : getCalculatedNcb(diffYear),
        newNcb:
          tempData?.policyType === "Third-party" ||
          temp_data?.newCar ||
          tempData?.policyType === "Not sure"
            ? "0%"
            : diffDays && diffDays > 90
            ? "0%"
            : temp_data?.prevShortTerm * 1
            ? temp_data?.ncb
            : temp_data?.newNcb && temp_data?.isNcbVerified === "Y"
            ? temp_data?.newNcb
            : getNewNcb(diffYear > 8 ? "50%" : getCalculatedNcb(diffYear)),
        ownerTypeId: owner?.value,
        expiry:
          staticOd &&
          temp_data?.previousPolicyTypeIdentifier !== "Y" &&
          (temp_data?.policyType === "Third-party" ||
            tempData?.policyType === "Third-party")
            ? moment(
                addYears(
                  subDays(
                    new Date(
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split("-")[2]
                      }`,
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split(
                          "-"
                        )[1] *
                          1 -
                        1
                      }`,
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split("-")[0]
                      }`
                    ),
                    1
                  ),
                  type === "car" ? 3 : 5
                )
              ).format("DD-MM-YYYY")
            : temp_data?.expiry,
        ...(TypeReturn(type) !== "cv" &&
          ((CheckForOD(InvoiceDate ? InvoiceDate : newRegDate) &&
            temp_data?.previousPolicyTypeIdentifier !== "Y" &&
            (temp_data?.policyType === "Third-party" ||
              tempData?.policyType === "Third-party")) ||
            (CheckForOD(InvoiceDate ? InvoiceDate : newRegDate) &&
              !temp_data?.odOnly) ||
            (!CheckForOD(InvoiceDate ? InvoiceDate : newRegDate) &&
              temp_data?.odOnly)) && {
            isExpiryModified: "registration",
          }),
      })
    );
    dispatch(
      setTempData({
        idvChoosed: 0,
        idvType: "lowIdv",
        ...(!["Not Sure", "Third-Party"].includes(tempData?.policyType) &&
          (((diffMonthsOdCar > 36 ||
            (diffMonthsOdCar === 36 && diffDaysOdCar > 1095)) &&
            TypeReturn(type) === "car") ||
            ((diffMonthsOdCar > 60 ||
              (diffMonthsOdCar === 60 && diffDaysOdCar > 1095)) &&
              TypeReturn(type) === "car")) && { policyType: "Comprehensive" }),
        expiry:
          staticOd &&
          temp_data?.previousPolicyTypeIdentifier !== "Y" &&
          temp_data?.policyType === "Third-party"
            ? moment(
                addYears(
                  subDays(
                    new Date(
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split("-")[2]
                      }`,
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split(
                          "-"
                        )[1] *
                          1 -
                        1
                      }`,
                      `${
                        (InvoiceDate ? InvoiceDate : newRegDate)?.split("-")[0]
                      }`
                    ),
                    1
                  ),
                  type === "car" ? 3 : 5
                )
              ).format("DD-MM-YYYY")
            : temp_data?.expiry,
      })
    );
    dispatch(CancelAll(false)); // resetting cancel all apis loading so quotes will restart (quotes apis)
    onClose(false);
  };

  const insData = !_.isEmpty(prevInsList)
    ? prevInsList?.map(({ previousInsurer }) => {
        return {
          label: previousInsurer,
          name: previousInsurer,
          value: previousInsurer,
          id: previousInsurer,
        };
      })
    : [];

  ///-----------------handling drawer mobile----------------------
  const [drawer, setDrawer] = useState(false);

  useEffect(() => {
    if (temp_data?.prevIcFullName) {
      let check = prevInsList?.filter(
        ({ previousInsurer }) => previousInsurer === temp_data?.prevIcFullName
      );
      let selected_option = check?.map(({ previousInsurer }) => {
        return {
          label: previousInsurer,
          name: previousInsurer,
          value: previousInsurer,
          id: previousInsurer,
        };
      });

      !_.isEmpty(selected_option) && setValue("preIc", selected_option[0]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.prevIcFullName, drawer]);

  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
  }, [show]);

  const owner = watch("ownerType");

  const content = (
    <>
      <ContentWrap>
        <ContentBox>
          <EditDetailsTop
            lessthan767={lessthan767}
            TypeReturn={TypeReturn}
            type={type}
            temp_data={temp_data}
          />
          <MMVSelection
            Theme1={Theme1}
            Controller={Controller}
            control={control}
            toDate={toDate}
            temp_data={temp_data}
            DateInput={DateInput}
            newRegDate={newRegDate}
            policyMin={policyMin}
            policyMax={policyMax}
            register={register}
            getYear={getYear}
            lessthan767={lessthan767}
            errors={errors}
            ErrorMsg={ErrorMsg}
            manufactureDate={manufactureDate}
            MultiSelect={MultiSelect}
            insData={insData}
            toDateOld={toDateOld}
            ownerType={ownerType}
            owner={owner}
            setValue={setValue}
            InvoiceDate={InvoiceDate}
            newManDate={newManDate}
            isEditable={isEditable}
          />
        </ContentBox>
        <PremChangeWarning>
          <div className="ncb_msg">
            <div className="image"></div>
            <p
              className="messagetxt"
              style={{ fontSize: "15px", fontWeight: "800" }}
            >
              {"Your premium will be updated based on your changes"}
              <b></b>.
            </p>
          </div>
        </PremChangeWarning>
        <UpdateButton onClick={handleSubmit(onSubmit)}>Update</UpdateButton>
      </ContentWrap>
    </>
  );

  return !lessthan767 ? (
    <Popup
      height={lessthan767 ? "100%" : "auto"}
      width={lessthan767 ? "100%" : "700px"}
      top="40%"
      show={show}
      onClose={onClose}
      content={content}
      position="middle"
      hiddenClose={tempData?.policyType ? false : true}
    />
  ) : (
    <>
      <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
        <Drawer
          anchor={"bottom"}
          open={drawer}
          onClose={() => {
            setDrawer(false);
            onClose(false);
          }}
          onOpen={() => setDrawer(true)}
          ModalProps={{
            keepMounted: true,
          }}
        >
          <MobileDrawerBody>
            <CloseButton
              onClick={() => {
                setDrawer(false);
                onClose(false);
              }}
            >
              <svg
                version="1.1"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                style={{ height: " 25px" }}
              >
                <path
                  fill={"#000"}
                  d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                ></path>
                <path fill="none" d="M0,0h24v24h-24Z"></path>
              </svg>
            </CloseButton>
            {content}
          </MobileDrawerBody>
        </Drawer>
      </React.Fragment>

      <GlobalStyle />
    </>
  );
};

// PropTypes
EditInfoPopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
EditInfoPopup.defaultProps = {
  show: false,
  onClose: () => {},
};

export default EditInfoPopup;
