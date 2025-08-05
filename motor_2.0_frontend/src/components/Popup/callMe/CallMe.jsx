import React, { useState, useEffect } from "react";
import PropTypes from "prop-types";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import Popup from "../Popup";
import { useForm } from "react-hook-form";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import ReactGA from "react-ga4";
import { CallUs } from "modules/Home/home.slice";
import { Content1, Content2, Content3 } from "./content";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const mobileValidation = yup.object({
  name: yup
    .string()
    .required("Name is Required")
    .matches(/^([A-Za-z\s])+$/, "Must contain only alphabets"),
  email: yup
    .string()
    .email("Please enter valid email id")
    .nullable()
    .transform((v, o) => (o === "" ? null : v))
    .matches(/^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$/, "Please enter proper Email"),
  mobileNo: yup
    .string()
    .nullable()
    .transform((v, o) => (o === "" ? null : v))
    .min(10, "Mobile No. should be 10 digits")
    .max(10, "Mobile No. should be 10 digits")
    .matches(/^[6-9][0-9]{9}$/, "Not valid number")
    .test("invalid", "Not valid number", (value) => {
      return !/^[9]{10}$/.test(value);
    }),
});

const CallMe = ({ show, onClose }) => {
  const { register, handleSubmit, errors, setValue, watch } = useForm({
    resolver: yupResolver(mobileValidation),
    mode: "onChange",
    reValidateMode: "onChange",
  });
  const { temp_data } = useSelector((state) => state.proposal);
  const { temp_data: userDataHome, theme_conf } = useSelector(
    (state) => state.home
  );

  const dispatch = useDispatch();
  const location = useLocation();
  const lessthan993 = useMediaPredicate("(max-width:993px)");
  const lessthan414 = useMediaPredicate("(max-width:414px)");
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const [msg, setMsg] = useState(false);

  const onSubmit = (data) => {
    setMsg(true);
    dispatch(
      CallUs({
        contactName: data.name,
        contactNo: data.mobileNo,
        email: data.email,
        enquiry_id: enquiry_id,
        link: window.location.href,
      })
    );

    setTimeout(() => {
      onClose(false);
      setMsg(false);
    }, 2500);
  };

  //prefill
  useEffect(() => {
    // name
    (userDataHome?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      temp_data?.userProposal?.additonalData?.owner?.email) &&
      setValue(
        "name",
        userDataHome?.firstName
          ? !userDataHome?.lastName
            ? userDataHome?.firstName
            : userDataHome?.firstName + " " + userDataHome?.lastName
          : userDataHome?.userProposal?.additonalData?.owner?.firstName
          ? !userDataHome?.userProposal?.additonalData?.owner?.lastName
            ? userDataHome?.userProposal?.additonalData?.owner?.firstName
            : userDataHome?.userProposal?.additonalData?.owner?.firstName +
              " " +
              userDataHome?.userProposal?.additonalData?.owner?.lastName
          : temp_data?.userProposal?.additonalData?.owner?.firstName
          ? !temp_data?.userProposal?.additonalData?.owner?.lastNameName
            ? temp_data?.userProposal?.additonalData?.owner?.firstName
            : temp_data?.userProposal?.additonalData?.owner?.firstName +
              " " +
              temp_data?.userProposal?.additonalData?.owner?.lastNameName
          : ""
      );
    // email
    (userDataHome?.emailId ||
      userDataHome?.userProposal?.additonalData?.owner?.email ||
      temp_data?.userProposal?.additonalData?.owner?.email) &&
      setValue(
        "email",
        userDataHome?.emailId
          ? userDataHome?.emailId
          : userDataHome?.userProposal?.additonalData?.owner?.email
          ? userDataHome?.userProposal?.additonalData?.owner?.email
          : temp_data?.userProposal?.additonalData?.owner?.email
      );

    // mobile no
    (userDataHome?.mobileNo ||
      userDataHome?.userProposal?.additonalData?.owner?.mobileNumber ||
      temp_data?.userProposal?.additonalData?.owner?.mobileNumber) &&
      setValue(
        "mobileNo",
        userDataHome?.mobileNo
          ? userDataHome?.mobileNo
          : userDataHome?.userProposal?.additonalData?.owner?.mobileNumber
          ? userDataHome?.userProposal?.additonalData?.owner?.mobileNumber
          : temp_data?.userProposal?.additonalData?.owner?.mobileNumber
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userDataHome]);

  // GA Event Throw
  useEffect(() => {
    if (show) {
      import.meta.env.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME !== "NA" &&
        ReactGA.event({
          category: "request_call_back_form_click",
          event: "request_call_back_form_click",
          action: "Click - Call me",
          action_type: "Click - Call me",
          business_lob: "Insurance",
          journey_status: "Feedback Stage",
        });
    }
  }, [show]);

  return (
    <>
      {!lessthan993 && (
        <Popup
          height={msg ? "200px" : "auto"}
          width="577px"
          show={show}
          onClose={onClose}
          content={
            msg ? (
              <Content2 />
            ) : import.meta.env?.VITE_BROKER === "SRIYAH" ||
              import.meta.env?.VITE_BROKER === "FYNTUNE" ||
              import.meta.env?.VITE_BROKER === "UIB" ||
              import.meta.env?.VITE_BROKER === "SRIDHAR" ? (
              <Content1
                handleSubmit={handleSubmit}
                onSubmit={onSubmit}
                register={register}
                errors={errors}
                watch={watch}
                lessthan414={lessthan414}
                theme_conf={theme_conf}
              />
            ) : (
              <Content3 Theme={Theme} theme_conf={theme_conf} />
            )
          }
          position="middle"
          animDuration="0s"
        />
      )}
    </>
  );
};

// PropTypes
CallMe.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
CallMe.defaultProps = {
  show: false,
  onClose: () => {},
};

export default CallMe;
