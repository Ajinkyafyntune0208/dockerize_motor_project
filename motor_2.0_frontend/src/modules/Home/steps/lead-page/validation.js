import * as yup from "yup";

export const validation = (theme_conf, selected) => {
  return {
    emailId: theme_conf?.broker_config?.lead_email
      ? yup
          .string()
          .email("Please enter valid email id")
          .required("Email id is required")
          .trim()
      : yup.string().email("Please enter valid email id").trim(),
    mobileNo:
      theme_conf?.broker_config?.mobileNo || selected
        ? yup
            .string()
            .nullable()
            .transform((v, o) => (o === "" ? null : v))
            .required("Mobile No. is required")
            .min(10, "Mobile No. should be 10 digits")
            .max(10, "Mobile No. should be 10 digits")
            .matches(/^[6-9][0-9]{9}$/, "Invalid mobile number")
        : yup
            .string()
            .nullable()
            .transform((v, o) => (o === "" ? null : v))
            .min(10, "Mobile No. should be 10 digits")
            .max(10, "Mobile No. should be 10 digits")
            .matches(/^[6-9][0-9]{9}$/, "Invalid mobile number"),
    lastName: yup
      .string()
      .nullable()
      .transform((v, o) => (o === "" ? null : v))
      .max(50, "Last Name can be upto 50 chars")
      .matches(/^([A-Za-z\s])+$/, "Must contain only alphabets")
      .trim(),
    firstName: yup
      .string()
      .nullable()
      .transform((v, o) => (o === "" ? null : v))
      .matches(/^([[0-9A-Za-z\s.'&_+@!#,-])+$/, "Entered value is invalid")
      .min(2, "Minimum 2 chars required")
      .max(50, "First Name can be upto 50 chars")
      .trim(),
    fullName: theme_conf?.broker_config?.fullName
      ? yup
          .string()
          .required("Name is required")
          .matches(/^([[0-9A-Za-z\s.'&_+@!#,-])+$/, "Entered value is invalid")
          .min(2, "Minimum 1 chars required")
          .max(101, "Must be less than 101 chars")
          .trim()
      : yup
          .string()
          .nullable()
          .transform((v, o) => (o === "" ? null : v))
          .matches(/^([[0-9A-Za-z\s.'&_+@!#,-])+$/, "Entered value is invalid")
          .min(1, "Minimum 1 chars required")
          .max(101, "Must be less than 101 chars")
          .trim(),
    whatsappNo: yup
      .string()
      .nullable()
      .transform((v, o) => (o === "" ? null : v))
      .min(10, "Whatsapp No. should be 10 digits")
      .max(10, "Whatsapp No. should be 10 digits")
      .matches(/^[6-9][0-9]{9}$/, "Invalid Whatsapp number"),
  };
};

export const isFullNameValid = (theme_conf, fullName, firstName, lastName) =>
  (fullName &&
    fullName.match(/^([0-9A-Za-z\s.'&_+@!#,-])+$/) &&
    fullName.length < 101 &&
    firstName?.length > 1 &&
    firstName?.length <= 50 &&
    (!lastName || (lastName.length <= 50 && lastName !== 0))) ||
  (!fullName && fullName !== 0 && !theme_conf?.broker_config?.fullName);

export const isEmailValid = (theme_conf, emailId) =>
  (emailId && emailId.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) ||
  (!emailId && emailId !== 0 && !theme_conf?.broker_config?.lead_email);

export const isMobileNoValid = (theme_conf, selected, mobileNo) =>
  (mobileNo && mobileNo.match(/^[6-9][0-9]{9}$/)) ||
  (!mobileNo &&
    mobileNo !== 0 &&
    !theme_conf?.broker_config?.mobileNo &&
    !selected);

export const isWhatsappNoValid = (whatsappNo) =>
  (whatsappNo && whatsappNo.match(/^[6-9][0-9]{9}$/)) ||
  (!whatsappNo && whatsappNo !== 0);
