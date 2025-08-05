import * as yup from "yup";
import _ from "lodash";
import swal from "sweetalert";
import { calculateExpression, compressPDF, handleCompress } from "./helper";
import { panMandatoryIC } from "../proposal-constants";
import { parse } from "date-fns";

//owner-card
export const ownerValidation = (
  temp_data,
  fields,
  ckycValue,
  poi,
  uploadFile,
  poi_identity,
  poa,
  poa_identity,
  identity,
  panAvailability
) => {
  return temp_data?.selectedQuote?.isRenewal === "Y"
    ? {
        addressLine1: yup.string().required("Address1 is Required").trim(),
        addressLine2: yup.string().trim(),
        ...((import.meta.env.VITE_BROKER !== "OLA" ||
          fields.includes("email")) && {
          email: yup
            .string()
            .email("Please enter valid email id")
            .min(2, "Please enter valid email id")
            .max(50, "Must be less than 50 chars")
            .required("Email id is required")
            .trim(),
        }),
        mobileNumber: yup
          .string()
          .required("Mobile No. is required")
          .matches(/^[6-9][0-9]{9}$/, "Invalid mobile number")
          .min(10, "Mobile No. should be 10 digits")
          .max(10, "Mobile No. should be 10 digits"),
        ...(Number(temp_data?.ownerTypeId) === 1
          ? {
              fullName: yup
                .string()
                .required("Name is required")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .min(1, "Minimum 1 chars required")
                .trim(),
              firstName: yup
                .string()
                .required("First Name is required")
                .matches(/^([[A-Za-z\s.'])+$/, "Must contain only alphabets")
                .min(1, "Minimum 1 chars required")
                .max(50, "First Name can be upto 50 chars")
                .trim(),
              lastName: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .max(50, "Last Name can be upto 50 chars")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .trim(),
            }
          : {
              firstName: yup
                .string()
                .required("Name is required")
                // .matches(
                //   /^([[0-9A-Za-z\s.'&_+@!#,(-)])+$/,
                //   "Entered value is invalid"
                // )
                .min(1, "Minimum 1 char required")
                .max(100, "Name can be upto 100 chars")
                .trim(),
              lastName: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .min(1, "Minimum 1 char required")
                .max(100, "Last Name can be upto 100 chars")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .trim(),
            }),
        ...(fields.includes("dob") &&
          Number(temp_data?.ownerTypeId) === 1 && {
            dob: yup.string().required("DOB is required"),
          }),
        ...(Number(temp_data?.ownerTypeId) === 2 && {
          cinNumber: yup
            .string()
            // .required("DOB is required")
            .matches(
              /^([L|U]{1})([0-9]{5})([A-Za-z]{2})([0-9]{4})([A-Za-z]{3})([0-9]{6})$/,
              "Invalid CIN Number"
            ),
        }),
        ...(fields.includes("fatherName") &&
          !fields.includes("relationType") &&
          fields.includes("ckyc") &&
          ckycValue === "NO" && {
            fatherName: yup
              .string()
              .nullable()
              .transform((v, o) => (o === "" ? null : v))
              .trim()
              .required("Father's Name is required"),
          }),
        ...(fields.includes("relationType") &&
          ((ckycValue === "NO" &&
            uploadFile &&
            ["iffco_tokio", "sbi"].includes(
              temp_data?.selectedQuote?.companyAlias
            )) ||
            (temp_data?.selectedQuote?.companyAlias === "magma" &&
              temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                "I")) && {
            relType: yup.string().required("Relation name is required"),
          }),
        ...(["sbi", "universal_sompo"].includes(
          temp_data?.selectedQuote?.companyAlias
        ) &&
          fields.includes("cisEnabled") && {
            ifsc: yup
              .string()
              .required("IFSC Code is required")
              .matches(/^[A-Za-z]{4}[a-zA-Z0-9]{7}$/, "Invalid IFSC Code"),
            accountNumber: yup
              .string()
              .required("Account Number is required")
              .matches(/^[a-zA-Z0-9]{11,17}$/, "Invalid Account Number"),
            bankName: yup
              .string()
              .required("Bank name is required")
              .matches(/^[A-Za-z0-9.,()"' &-]+$/, "Invalid Bank Name"),
            ...(temp_data?.selectedQuote?.companyAlias === "sbi" && {
              branchName: yup.string().required("Branch name is required"),
            }),
          }),
        ...(fields.includes("ckyc") &&
        ckycValue === "YES" &&
        !["oriental"].includes(temp_data?.selectedQuote?.companyAlias)
          ? {
              ckycNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .trim()
                .matches(/^[0-9]+$/, "Must be only digits")
                .required("CKYC Number is required")
                .min(14, "CKYC No. consist of 14 digits")
                .max(14, "CKYC No. consist of 14 digits"),
            }
          : {}),
        ...(fields.includes("ckyc") &&
        (fields.includes("poi") || poi) &&
        ckycValue === "NO" &&
        uploadFile
          ? poi_identity === "drivingLicense"
            ? {
                poi_drivingLicense: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .trim()
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .required("Driving License is required"),
              }
            : poi_identity === "panNumber"
            ? {
                poi_panNumber: yup
                  .string()
                  .required("PAN is required")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                    "PAN number invalid"
                  )
                  .trim(),
              }
            : poi_identity === "gstNumber"
            ? {
                poi_gstNumber: yup
                  .string()
                  .required("GST is required")
                  .matches(
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                    "GST number invalid"
                  )
                  .trim(),
              }
            : poi_identity === "voterId"
            ? {
                poi_voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : poi_identity === "passportNumber"
            ? {
                poi_passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : poi_identity === "aadharNumber"
            ? {
                poi_aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),
        ...(fields.includes("ckyc") &&
        (fields.includes("poa") || poa) &&
        ckycValue === "NO"
          ? poa_identity === "drivingLicense" && uploadFile
            ? {
                poa_drivingLicense: yup
                  .string()
                  .nullable()
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .transform((v, o) => (o === "" ? null : v))
                  .trim()
                  .required("Driving License is required"),
              }
            : poa_identity === "panNumber"
            ? {
                poa_panNumber: yup
                  .string()
                  .required("PAN is required")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                    "PAN number invalid"
                  )
                  .trim(),
              }
            : poa_identity === "gstNumber"
            ? {
                poa_gstNumber: yup
                  .string()
                  .required("GST is required")
                  .matches(
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                    "GST number invalid"
                  )
                  .trim(),
              }
            : poa_identity === "voterId"
            ? {
                poa_voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : poa_identity === "passportNumber"
            ? {
                poa_passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : poa_identity === "aadharNumber"
            ? {
                poa_aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),
        ...(fields.includes("ckyc")
          ? identity === "drivingLicense" && ckycValue === "NO" && !uploadFile
            ? {
                drivingLicense: yup
                  .string()
                  .nullable()
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .transform((v, o) => (o === "" ? null : v))
                  .trim()
                  .required("Driving License is required"),
              }
            : identity === "voterId" && ckycValue === "NO"
            ? {
                voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : identity === "passportNumber" && ckycValue === "NO"
            ? {
                passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : identity === "aadharNumber" &&
              ckycValue === "NO" &&
              poi_identity !== "aadharNumber" &&
              poa_identity !== "aadharNumber"
            ? {
                aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),
        ...(!fields.includes("panNumber")
          ? {}
          : ((poi_identity !== "panNumber" &&
              ckycValue === "NO" &&
              fields.includes("ckyc") &&
              identity &&
              identity === "panNumber") ||
              temp_data?.selectedQuote?.totalPayableAmountWithAddon > 100000 ||
              temp_data?.selectedQuote?.finalPayableAmount >= 100000 ||
              panMandatoryIC.includes(temp_data?.selectedQuote?.companyAlias) ||
              (["bajaj_allianz", "sbi", "universal_sompo"].includes(
                temp_data?.selectedQuote?.companyAlias
              ) &&
                ckycValue === "NO" &&
                poi_identity !== "panNumber")) &&
            panAvailability === "YES"
          ? {
              panNumber: yup
                .string()
                .required("PAN is required")
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                  "PAN number invalid"
                )
                .trim(),
            }
          : {
              panNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                  "PAN number invalid"
                )
                .trim(),
            }),

        ...(!fields.includes("gstNumber")
          ? {}
          : fields.includes("ckyc") &&
            ((identity === "gstNumber" &&
              ckycValue === "NO" &&
              poi_identity !== "gstNumber" &&
              poa_identity !== "gstNumber") ||
              ([
                "united_india",
                "kotak",
                "liberty_videocon",
                "royal_sundaram",
              ].includes(temp_data?.selectedQuote?.companyAlias) &&
                temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                  "C"))
          ? {
              gstNumber: yup
                .string()
                .required("GST is required")
                .matches(
                  /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                  "GST number invalid"
                )
                .trim(),
            }
          : {
              gstNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                  "GST number invalid"
                )
                .trim(),
            }),
        pincode: yup
          .string()
          .required("Pincode is Required")
          .matches(/^[0-9]+$/, "Must be only digits")
          .min(6, "Must be 6 digits")
          .max(6, "Must be 6 digits")
          .trim(),
        city: yup
          .string()
          .required("Required")
          .matches(/[1-9A-Za-z]/, "City Required"),
        state: yup
          .string()
          .required("Required")
          .matches(/[1-9A-Za-z]/, "State Required"),
      }
    : {
        pincode: yup
          .string()
          .required("Pincode is Required")
          .matches(/^[0-9]+$/, "Must be only digits")
          .min(6, "Must be 6 digits")
          .max(6, "Must be 6 digits")
          .trim(),
        addressLine1: yup.string().required("Address1 is Required").trim(),
        addressLine2: yup.string().trim(),
        city: yup
          .string()
          .required("Required")
          .matches(/[1-9A-Za-z]/, "City Required"),
        state: yup
          .string()
          .required("Required")
          .matches(/[1-9A-Za-z]/, "State Required"),
        cityId: yup.string().required("Required"),
        stateId: yup.string().required("Required"),
        ...(!fields.includes("gstNumber")
          ? {}
          : fields.includes("ckyc") &&
            ((identity === "gstNumber" &&
              ckycValue === "NO" &&
              poi_identity !== "gstNumber" &&
              poa_identity !== "gstNumber") ||
              ([
                "united_india",
                "kotak",
                "liberty_videocon",
                "royal_sundaram",
              ].includes(temp_data?.selectedQuote?.companyAlias) &&
                temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                  "C"))
          ? {
              gstNumber: yup
                .string()
                .required("GST is required")
                .matches(
                  /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                  "GST number invalid"
                )
                .trim(),
            }
          : {
              gstNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                  "GST number invalid"
                )
                .trim(),
            }),
        ...(Number(temp_data?.ownerTypeId) === 2 && {
          cinNumber: yup
            .string()
            .matches(
              /^([L|U]{1})([0-9]{5})([A-Za-z]{2})([0-9]{4})([A-Za-z]{3})([0-9]{6})$/,
              "Invalid CIN Number"
            ),
        }),
        ...(fields.includes("fatherName") &&
          !fields.includes("relationType") &&
          ckycValue === "NO" && {
            fatherName: yup
              .string()
              .nullable()
              .transform((v, o) => (o === "" ? null : v))
              .trim()
              .required("Father's Name is required"),
          }),
        ...(fields.includes("relationType") &&
          ((ckycValue === "NO" &&
            uploadFile &&
            ["iffco_tokio", "sbi"].includes(
              temp_data?.selectedQuote?.companyAlias
            )) ||
            (temp_data?.selectedQuote?.companyAlias === "magma" &&
              temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                "I")) && {
            relType: yup.string().required("Relation name is required"),
          }),
        ...(["sbi", "universal_sompo"].includes(
          temp_data?.selectedQuote?.companyAlias
        ) &&
          fields.includes("cisEnabled") && {
            ifsc: yup
              .string()
              .required("IFSC Code is required")
              .matches(/^[A-Za-z]{4}[a-zA-Z0-9]{7}$/, "Invalid IFSC Code"),
            accountNumber: yup
              .string()
              .required("Account Number is required")
              .matches(/^[a-zA-Z0-9]{11,17}$/, "Invalid Account Number"),
            ...(temp_data?.selectedQuote?.companyAlias === "sbi" && {
              branchName: yup.string().required("Branch name is required"),
            }),
          }),
        ...(fields.includes("ckyc") &&
        ckycValue === "YES" &&
        !["oriental"].includes(temp_data?.selectedQuote?.companyAlias)
          ? {
              ckycNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .trim()
                .required("CKYC Number is required")
                .matches(/^[0-9]+$/, "Must be only digits")
                .min(14, "CKYC No. consist of 14 characters")
                .max(14, "CKYC No. consist of 14 characters"),
            }
          : {}),
        ...(fields.includes("ckyc") && ckycValue === "NO" && !uploadFile
          ? identity === "drivingLicense"
            ? {
                drivingLicense: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .trim()
                  .required("Driving License is required"),
              }
            : identity === "voterId" && ckycValue === "NO"
            ? {
                voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : identity === "passportNumber" && ckycValue === "NO"
            ? {
                passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : identity === "aadharNumber" &&
              ckycValue === "NO" &&
              poi_identity !== "aadharNumber" &&
              poa_identity !== "aadharNumber"
            ? {
                aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),
        ...(fields.includes("ckyc") &&
        (fields.includes("poi") || poi) &&
        ckycValue === "NO" &&
        uploadFile
          ? poi_identity === "drivingLicense"
            ? {
                poi_drivingLicense: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .trim()
                  .required("Driving License is required"),
              }
            : poi_identity === "panNumber"
            ? {
                poi_panNumber: yup
                  .string()
                  .required("PAN is required")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                    "PAN number invalid"
                  )
                  .trim(),
              }
            : poi_identity === "gstNumber"
            ? {
                poi_gstNumber: yup
                  .string()
                  .required("GST is required")
                  .matches(
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                    "GST number invalid"
                  )
                  .trim(),
              }
            : poi_identity === "voterId"
            ? {
                poi_voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : poi_identity === "passportNumber"
            ? {
                poi_passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : poi_identity === "aadharNumber"
            ? {
                poi_aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),
        ...(fields.includes("ckyc") &&
        (fields.includes("poa") || poa) &&
        ckycValue === "NO" &&
        uploadFile
          ? poa_identity === "drivingLicense"
            ? {
                poa_drivingLicense: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  // .matches(
                  //   /^(([A-Z]{2}[0-9]{2})|([A-Z]{2}-[0-9]{2}))([\s-])((19|20)[0-9][0-9])[0-9]{7}$/,
                  //   "Driving License Invalid"
                  // )
                  .trim()
                  .required("Driving License is required"),
              }
            : poa_identity === "panNumber"
            ? {
                poa_panNumber: yup
                  .string()
                  .required("PAN is required")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                    "PAN number invalid"
                  )
                  .trim(),
              }
            : poa_identity === "gstNumber"
            ? {
                poa_gstNumber: yup
                  .string()
                  .required("GST is required")
                  .matches(
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                    "GST number invalid"
                  )
                  .trim(),
              }
            : poa_identity === "voterId"
            ? {
                poa_voterId: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^([a-zA-Z]){3}([0-9]){7}?$/g, "Voter ID Invalid")
                  .trim()
                  .required("Voter ID is required"),
              }
            : poa_identity === "passportNumber"
            ? {
                poa_passportNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(
                    /^[A-PR-WY][1-9]\d\s?\d{4}[1-9]$/gi,
                    "Passport Number Invalid"
                  )
                  .trim()
                  .required("Passport Number is required"),
              }
            : poa_identity === "aadharNumber"
            ? {
                poa_aadharNumber: yup
                  .string()
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/^\d{4}\d{4}\d{4}$/, "Adhaar Number Invalid")
                  .trim()
                  .required("Adhaar Number is required"),
              }
            : {}
          : {}),

        ...(!fields.includes("panNumber")
          ? {}
          : ((poi_identity !== "panNumber" &&
              ckycValue === "NO" &&
              fields.includes("ckyc") &&
              identity &&
              identity === "panNumber") ||
              temp_data?.selectedQuote?.totalPayableAmountWithAddon > 100000 ||
              temp_data?.selectedQuote?.finalPayableAmount >= 100000 ||
              panMandatoryIC.includes(temp_data?.selectedQuote?.companyAlias) ||
              (["bajaj_allianz", "sbi", "universal_sompo"].includes(
                temp_data?.selectedQuote?.companyAlias
              ) &&
                ckycValue === "NO" &&
                poi_identity !== "panNumber")) &&
            panAvailability === "YES"
          ? {
              panNumber: yup
                .string()
                .required("PAN is required")
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                  "PAN number invalid"
                )
                .trim(),
            }
          : {
              panNumber: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .matches(
                  /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/,
                  "PAN number invalid"
                )
                .trim(),
            }),
        ...(fields.includes("email") && {
          email: yup
            .string()
            .email("Please enter valid email id")
            .min(2, "Please enter valid email id")
            .max(50, "Must be less than 50 chars")
            .required("Email id is required")
            .trim(),
        }),
        mobileNumber: yup
          .string()
          .required("Mobile No. is required")
          .matches(/^[6-9][0-9]{9}$/, "Invalid mobile number")
          .min(10, "Mobile No. should be 10 digits")
          .max(10, "Mobile No. should be 10 digits"),
        ...(fields.includes("dob") &&
          Number(temp_data?.ownerTypeId) === 1 && {
            dob: yup.string().required("DOB is required"),
          }),
        ...(fields.includes("gender") &&
          Number(temp_data?.ownerTypeId) === 1 && {
            gender: yup
              .string()
              .required("Gender is required")
              .matches(
                /^([A-Za-z\s])+$/,
                "Salutation and gender doesn't match"
              ),
          }),
        ...(Number(temp_data?.ownerTypeId) === 1
          ? {
              fullName: yup
                .string()
                .required("Name is required")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .min(2, "Minimum 2 chars required")
                .trim(),
              firstName: yup
                .string()
                .required("First Name is required")
                .matches(/^([[A-Za-z\s.'])+$/, "Must contain only alphabets")
                .min(1, "Minimum 2 chars required")
                .max(50, "Must be less than 50 chars")
                .trim(),
              lastName: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .max(50, "Must be less than 50 chars")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .trim(),
            }
          : {
              firstName: yup
                .string()
                .required("Name is required")
                // .matches(
                //   /^([[0-9A-Za-z\s.'&_+@!#,(-)])+$/,
                //   "Must be only AlphaNumeric"
                // )
                .min(1, "Minimum 1 char required")
                .max(100, "Must be less than 100 chars")
                .trim(),
              lastName: yup
                .string()
                .nullable()
                .transform((v, o) => (o === "" ? null : v))
                .min(1, "Minimum 1 char required")
                .max(100, "Must be less than 100 chars")
                .matches(/^([A-Za-z\s.'])+$/, "Must contain only alphabets")
                .trim(),
            }),
        ...(fields.includes("maritalStatus") &&
          Number(temp_data?.ownerTypeId) === 1 && {
            maritalStatus: yup.string().required("Marital Status is required"),
          }),
        ...(fields.includes("occupation") &&
          Number(temp_data?.ownerTypeId) === 1 && {
            occupation: yup
              .string()
              .required("Occupation is required")
              .matches(/[^@]/, "Occupation is required"),
          }),
      };
};

//Nominee-card
//prettier-ignore
export const nomineeValidation = (temp_data, fields, cpaToggle, NomineeBroker, pucVal) => {
    return (temp_data?.selectedQuote?.isRenewal === "Y" &&
    fields.includes("cpaOptIn")) ||
    (!cpaToggle &&
      !NomineeBroker &&
      temp_data?.corporateVehiclesQuoteRequest?.policyType !== "own_damage" &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C" &&
      fields.includes("cpaOptIn"))
    ? {
        cpa: yup.string().required("selection is required"),
        ...(cpaToggle &&
          temp_data?.corporateVehiclesQuoteRequest?.policyType !==
            "own_damage" &&
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !==
            "C" &&
          fields.includes("cpaOptIn") && {
            nomineeName: yup
              .string()
              .required("Nominee Name is required")
              .min(2, "Minimum 2 chars required")
              .matches(/^([A-Za-z\s])+$/, "Must contain only alphabets")
              .trim(),
            nomineeDob: yup.string().required("DOB is required"),
            nomineeRelationship: yup
              .string()
              .required("Nominee Relation Required")
              .matches(/[^@]/, "Nominee Relation Required"),
          }),
      }
    : {
        nomineeName: yup
          .string()
          .required("Nominee Name is required")
          .min(2, "Minimum 2 chars required")
          .matches(/^([A-Za-z\s])+$/, "Must contain only alphabets")
          .trim(),
        nomineeDob: yup.string().required("DOB is required"),
        nomineeRelationship: yup
          .string()
          .required("Nominee Relation Required")
          .matches(/[^@]/, "Nominee Relation Required"),
      }
}

//vehicle-card
//prettier-ignore
export const vehicleValidation = (temp_data, addValidation, financeValidation, pucVal, type,
enginePattern, validations, engineVal, chassisPattern, chasisVal, fields) => {
  return temp_data?.selectedQuote?.isRenewal === "Y"
    ? {   
        ...(addValidation && {
          inspectionType : yup.string().required("Inspection Type is Required"),
          carRegistrationCityId: yup.string().required("city id is Required"),  
          carRegistrationStateId: yup.string().required("state id is Required"),
          carRegistrationPincode: yup
            .string()
            .required("Pincode is Required")
            .matches(/^[0-9]+$/, "Must be only digits")
            .min(6, "Must be 6 digits")
            .max(6, "Must be 6 digits"),
          carRegistrationAddress1: yup
            .string()
            .max(30, "Max 30 chars allowed")
            .required("Address1 is Required")
            .trim(),
          carRegistrationAddress2: yup
            .string()
            .max(30, "Max 30 chars allowed")
            .required("Address2 is Required")
            .trim(),
          carRegistrationCity: yup
            .string()
            .required("Required")
            .matches(/[1-9A-Za-z]/, "City Required"),
          carRegistrationState: yup
            .string()
            .required("Required")
            .matches(/[1-9A-Za-z]/, "State Required"),
        }),
        ...(fields.includes("hazardousType") &&
          temp_data?.parent?.productSubTypeCode === "GCV" && {
            hazardousType: yup.string().required("Hazardous Type is required"),
          }),
        ...(financeValidation && {
          nameOfFinancer: yup.string().required("Financer is required"),
          financer_sel: yup.array().required("Financer is required").defined(),
          financerAgreementType: yup
            .string()
            .required("Financer Type is required")
            .matches(/[^@]/, "Financer Type is required"),
          ...(temp_data?.selectedQuote?.companyAlias === "shriram"
            ? {
                ...(fields.includes("hypothecationCity") && {
                  hypothecationCity: yup
                    .string()
                    .max(12, "Maximum 12 characters allowed")
                    .required("Financer city/branch is required")
                    .trim(),
                }),
              }
            : {
                ...(fields.includes("hypothecationCity") && {
                  hypothecationCity: yup
                    .string()
                    .required("Financer city/branch is required")
                    .trim(),
                }),
              }),
        }),
      }
    : {
        ...(addValidation && {
          carRegistrationCityId: yup.string().required("city id is Required"),
          carRegistrationStateId: yup.string().required("state id is Required"),
          carRegistrationPincode: yup
            .string()
            .required("Pincode is Required")
            .matches(/^[0-9]+$/, "Must be only digits")
            .min(6, "Must be 6 digits")
            .max(6, "Must be 6 digits"),
          carRegistrationAddress1: yup
            .string()
            .max(30, "Max 30 chars allowed")
            .required("Address1 is Required")
            .trim(),
          carRegistrationAddress2: yup
            .string()
            .max(30, "Max 30 chars allowed")
            .required("Address2 is Required")
            .trim(),
          carRegistrationCity: yup
            .string()
            .required("Required")
            .matches(/[1-9A-Za-z]/, "City Required"),
          carRegistrationState: yup
            .string()
            .required("Required")
            .matches(/[1-9A-Za-z]/, "State Required"),
        }),
        ...{
          ...(((fields.includes("pucNo") &&
            temp_data?.corporateVehiclesQuoteRequest?.businessType !==
              "newbusiness" &&
            temp_data?.selectedQuote?.companyAlias !== "royal_sundaram" &&
            temp_data?.selectedQuote?.companyAlias !== "hdfc_ergo" &&
            temp_data?.selectedQuote?.companyAlias !== "tata_aig") ||
            (fields.includes("pucNo") &&
              ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
                temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
                temp_data?.corporateVehiclesQuoteRequest?.businessType !==
                  "newbusiness") ||
                pucVal))) && {
            pucNo: yup.string().required("PUC is required").trim(),
          }),
          ...(((fields.includes("pucExpiry") &&
            temp_data?.corporateVehiclesQuoteRequest?.businessType !==
              "newbusiness" &&
            temp_data?.selectedQuote?.companyAlias !== "tata_aig") ||
            (fields.includes("pucExpiry") &&
              ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
                temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
                temp_data?.corporateVehiclesQuoteRequest?.businessType !==
                  "newbusiness") ||
                pucVal))) && {
            pucExpiry: yup.string().required("PUC expiry is required").trim(),
          }),
        },
        ...(temp_data?.selectedQuote?.maxBodyIDV && {
          chassisIdv: yup
            .number()
            .typeError("Please enter a valid number")
            .min(
              temp_data?.selectedQuote?.minChassisIDV,
              `Number must be greater than or equal to ${temp_data?.selectedQuote?.minChassisIDV}`
            )
            .max(
              temp_data?.selectedQuote?.maxChassisIDV,
              `Number must be less than or equal to ${temp_data?.selectedQuote?.maxChassisIDV}`
            )
            .nullable()
            .transform((v, o) => (o === "" ? null : v)),
          bodyIdv: yup
            .number()
            .typeError("Please enter a valid number")
            .min(
              temp_data?.selectedQuote?.minBodyIDV,
              `Number must be greater than or equal to ${temp_data?.selectedQuote?.minBodyIDV}`
            )
            .max(
              temp_data?.selectedQuote?.maxBodyIDV,
              `Number must be less than or equal to ${temp_data?.selectedQuote?.maxBodyIDV}`
            )
            .nullable()
            .transform((v, o) => (o === "" ? null : v)),
        }),
        ...((temp_data?.selectedQuote?.companyAlias === "sbi" &&
          type !== "cv") ||
        ["sbi", "universal_sompo", "new_india", "oriental"].includes(
          temp_data?.selectedQuote?.companyAlias
        )
          ? {
              ...(fields.includes("vehicleColor") && {
                vehicleColor: yup
                  .string()
                  .required("Color is required")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .matches(/[^@]/, "Input must consist of alphabets only")
                  .trim(),
              }),
            }
          : !["sbi", "universal_sompo", "new_india", "oriental"].includes(
              temp_data?.selectedQuote?.companyAlias
            )
          ? {
              ...(fields.includes("vehicleColor") && {
                vehicleColor: yup
                  .string()
                  .matches(
                    /^[A-Za-z-/\s*]+$/,
                    "Input must consist of alphabets only"
                  )
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v))
                  .trim(),
              }),
            }
          : {
              ...(fields.includes("vehicleColor") && {
                vehicleColor: yup.string().nullable().trim(),
              }),
            }),
        vehicaleRegistrationNumber: yup
          .string()
          .required("Registration Number is required"),
        engineNumber: yup
          .string()
          .required(
            temp_data?.corporateVehiclesQuoteRequest?.fuelType === "ELECTRIC"
              ? "Battery Number is required"
              : "Engine Number is required"
          )
          .matches(
            enginePattern,
            validations?.engineRegxFailureMsg || "Invalid Engine Number"
          )
          .min(engineVal.minlen, engineVal.textmin)
          .max(engineVal?.maxlen, engineVal?.textmax)
          .trim(),
        vehicleManfYear: yup.string().required("Manufacture year is required"),
        chassisNumber: yup
          .string()
          .required("Chassis Number is required")
          .matches(
            chassisPattern,
            validations?.chassisRegxFailureMsg || "Invalid Chassis Number"
          )
          .min(chasisVal.minlen, chasisVal.textmin)
          .max(chasisVal?.maxlen, chasisVal?.textmax)
          .trim(),
        ...(temp_data?.regNo !== "NEW"
          ? (temp_data?.regNo && temp_data?.regNo[0] * 1) || ""
            ? //BH Validation
              {
                regNo: yup.string().required("Registration No. is required"),
              }
            : {
                regNo1: yup.string().required("Registration No. is required"),
                regNo2: yup
                  .string()
                  .matches(/^[A-Z\s]{1,3}$/, "Invalid Number")
                  .nullable()
                  .transform((v, o) => (o === "" ? null : v)),
                regNo3: yup
                  .string()
                  .required("Number is required")
                  .matches(/^[0-9]{4}$/, "Invalid Number"),
              }
          : {}),
        ...((Number(temp_data?.quoteLog?.icId) === 20 ||
          temp_data?.selectedQuote?.companyAlias === "reliance") &&
          type === "cv" &&
          Number(temp_data?.productSubTypeId) === 6 &&
          temp_data?.parent?.productSubTypeCode !== "GCV" && {
            vehicleUsageType: yup
              .string()
              .required("Usage Type is required")
              .matches(/[^@]/, "Usage Type is required"),
            vehicleCategory: yup
              .string()
              .required("Category Type is required")
              .matches(/[^@]/, "Category Type is required"),
          }),
        ...(financeValidation && {
          nameOfFinancer: yup.string().required("Financer is required"),
          financerAgreementType: yup
            .string()
            .required("Financer Type is required")
            .matches(/[^@]/, "Financer Type is required"),
          financer_sel: yup.array().required("Financer is required").defined(),
          ...(temp_data?.selectedQuote?.companyAlias === "shriram"
            ? {
                ...(fields.includes("hypothecationCity") && {
                  hypothecationCity: yup
                    .string()
                    .max(12, "Maximum 12 characters allowed")
                    .required("Financer city/branch is required")
                    .matches(
                      /^([1-9A-Za-z\s/'-.~*)(:,])+$/,
                      "Invalid city/branch name"
                    )
                    .trim(),
                }),
              }
            : {
                ...(fields.includes("hypothecationCity") && {
                  hypothecationCity: yup
                    .string()
                    .required("Financer city/branch is required")
                    .matches(
                      temp_data?.selectedQuote?.companyAlias === "united_india" ? /.*/ : /^([1-9A-Za-z\s/'-.~*)(:,])+$/,
                      "Invalid city/branch name"
                    )
                    .trim(),
                }),
              }),
        }),
      };
};

//configurable chassis validation
export const ChassisValidation = (validations, temp_data) => {
  return {
    minlen: !_.isEmpty(validations)
      ? validations?.chasisNomin
      : temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
        "NEW"
      ? 5
      : 17,
    textmin: !_.isEmpty(validations)
      ? `Minimum ${validations?.chasisNomin} characters required`
      : temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
        "NEW"
      ? "Minimum 5 characters required"
      : "Minimum 17 characters required",
    maxlen: !_.isEmpty(validations) ? validations?.chasisNomax : 25,
    textmax: !_.isEmpty(validations)
      ? `Maximum ${validations?.chasisNomax} characters allowed`
      : "Maximum 25 characters allowed",
  };
};

//configurable engine validation
export const EngineValidation = (validations) => {
  return {
    minlen: !_.isEmpty(validations) ? validations?.engineNomin : 6,
    textmin: !_.isEmpty(validations)
      ? `Minimum ${validations?.engineNomin} characters required`
      : "Minimum 6 characters required",
    maxlen: !_.isEmpty(validations) ? validations?.engineNomax : 25,
    textmax: !_.isEmpty(validations)
      ? `Maximum ${validations?.engineNomax} characters allowed`
      : "Maximum 25 characters allowed",
  };
};

//policy-card
//prettier-ignore
export const policyValidation = (temp_data, prevPolicyCon, PolicyValidationExculsion, OwnDamage, PACon, reasonCpa) => {
    return temp_data?.selectedQuote?.isRenewal === "Y"
      ? {

        ...(PACon &&
          reasonCpa !== "I do not have a valid driving license." && {
            cPAInsComp: yup
              .string()
              .required("Insurance company is required")
              .matches(/^[^@]+$/, "Insurance company is required"),
            cPAPolicyFmDt: yup.string().required("Date is required"),
            cPAPolicyToDt: yup
            .string()
            .required('Date is required')
            .when(['temp_data.corporateVehiclesQuoteRequest.businessType', 'prevPolicyExpiryDate'], {
              is: (businessType, prevPolicyExpiryDate) => businessType === 'newbusiness' || !prevPolicyExpiryDate,
              then: yup.string().test(
                'is-greater-than-or-equal-to-current-date',
                'End date cannot be lesser than the current date',
                function (value) {
                  const currentDate = new Date().setHours(0,0,0,0);
                  const endDate = parse(value, 'dd-MM-yyyy', currentDate).setHours(0,0,0,0);
                  return endDate >= currentDate;
                }
              ),
              otherwise: yup.string().test(
                'is-greater-than-or-equal',
                'End date must be greater than or equal to previous policy expiry date',
                function (value) {
                  const prevPolicyExpiryDate = parse(this.parent.prevPolicyExpiryDate, 'dd-MM-yyyy', new Date()).setHours(0,0,0,0);
                  const endDate = parse(value, 'dd-MM-yyyy', new Date()).setHours(0,0,0,0);
                  return endDate >= prevPolicyExpiryDate;
                }
              ),
            }),
            cPAPolicyNo: yup.string().required("Policy number is required"),
            cPASumInsured: yup
              .string()
              .required("Sum Insured is required")
              .trim()
              .test(
                "cPASumInsured",
                "Sum insured should be above 1500000",
                (value) => {
                  return value >= 1500000;
                }
              ),
          }),
      }
      : {
          previousNcb: yup
            .string()
            .nullable()
            .transform((v, o) => (o === "" ? null : v))
            .matches(/^[^@]+$/, "Previous NCB is required"),
          ...(prevPolicyCon &&
            !PolicyValidationExculsion && {
              previousInsuranceCompany: yup
                .string()
                .required("Previous insurance company is required")
                .matches(/^[^@]+$/, "Previous insurance company is required"),
              prevPolicyExpiryDate: yup
                .string()
                .required("Expiry date is required"),
              previousPolicyNumber: yup
                .string()
                .required("Policy number is required")
                .min(5, "Minimum 5 chars are required")
                .max(
                  temp_data?.selectedQuote?.companyAlias === "sbi" ? 40 : 40,
                  temp_data?.selectedQuote?.companyAlias === "sbi"
                    ? "Maximum 40 chars allowed"
                    : "Maximum 40 chars allowed "
                ),
            }),
          ...((temp_data?.prevShortTerm &&
            prevPolicyCon && {
              previousPolicyStartDate: yup
                .string()
                .required("Start date is required"),
            }) ||
            {}),
          ...(prevPolicyCon &&
            OwnDamage && {
              tpInsuranceCompany: yup
                .string()
                .required("TP insurance company is required")
                .matches(/^[^@]+$/, "TP insurance company is required"),
              tpStartDate: yup.string().required("TP start date is required"),
              tpEndDate: yup.string().required("TP end date is required"),
              tpInsuranceNumber: yup
                .string()
                .required("TP policy number is required")
                .min(5, "Minimum 5 chars are required")
                .max(40, "Maximum 40 chars allowed "),
            }),
          ...(PACon &&
            reasonCpa !== "I do not have a valid driving license." && {
              cPAInsComp: yup
                .string()
                .required("Insurance company is required")
                .matches(/^[^@]+$/, "Insurance company is required"),
              cPAPolicyFmDt: yup.string().required("Date is required"),
              cPAPolicyToDt: yup
              .string()
              .required('Date is required')
              .when(['temp_data.corporateVehiclesQuoteRequest.businessType', 'prevPolicyExpiryDate'], {
                is: (businessType, prevPolicyExpiryDate) => businessType === 'newbusiness' || !prevPolicyExpiryDate,
                then: yup.string().test(
                  'is-greater-than-or-equal-to-current-date',
                  'End date cannot be lesser than the current date',
                  function (value) {
                    const currentDate = new Date().setHours(0,0,0,0);
                    const endDate = parse(value, 'dd-MM-yyyy', currentDate).setHours(0,0,0,0);
                    return endDate >= currentDate;
                  }
                ),
                otherwise: yup.string().test(
                  'is-greater-than-or-equal',
                  'End date must be greater than or equal to previous policy expiry date',
                  function (value) {
                    const prevPolicyExpiryDate = parse(this.parent.prevPolicyExpiryDate, 'dd-MM-yyyy', new Date()).setHours(0,0,0,0);
                    const endDate = parse(value, 'dd-MM-yyyy', new Date()).setHours(0,0,0,0);
                    return endDate >= prevPolicyExpiryDate;
                  }
                ),
              }),
              cPAPolicyNo: yup.string().required("Policy number is required"),
              cPASumInsured: yup
                .string()
                .required("Sum Insured is required")
                .trim()
                .test(
                  "cPASumInsured",
                  "Sum insured should be above 1500000",
                  (value) => {
                    return value >= 1500000;
                  }
                ),
            }),
        }
  
}

//form-section
//photo validation
export const photoValidation = (
  companyAlias,
  photo,
  setPhoto,
  validationConfig
) => {
  const companyValidationRules = validationConfig.find(
    (rule) => rule.ic === companyAlias
  );
  if (!companyValidationRules) {
    const defaultMaxSize = 2 * 1024 * 1024;
    const defaultExtensions = [".jpg", ".jpeg", ".png"];

    // Validate file extension
    const allowedExtensions = defaultExtensions.map((ext) => ext.toLowerCase());
    const fileExtension = photo?.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(`.${fileExtension}`)) {
      swal(
        "Error",
        `Please upload photo with ${defaultExtensions.join(", ")} extension`,
        "error"
      ).then(() => {
        setPhoto();
      });
      return;
    }
    const maxSizeMB = defaultMaxSize / 1024 / 1024;

    // Validate file size
    if (photo?.size > defaultMaxSize) {
      if (photo?.type === "application/pdf") {
        swal(
          "Error",
          `Your file is too large. We are not accepting more than ${maxSizeMB} MB.`,
          "error",
          {
            buttons: {
              cancel: true,
              compress: {
                text: "Compress",
                value: "compress",
              },
            },
          }
        ).then((value) => {
          if (value === "compress") {
            if (photo?.size > defaultMaxSize * 2) {
              swal(
                "Error",
                `Your file is too large and unable to compress.`,
                "error"
              ).then(() => {
                setPhoto();
              });
              return;
            }
            swal(
              "Confirm",
              "Compressing will reduce document size by 60%. Do you want to proceed?",
              "warning",
              {
                buttons: {
                  cancel: true,
                  confirm: {
                    text: "Confirm",
                    value: "confirm",
                  },
                },
              }
            ).then(async (confirmValue) => {
              if (confirmValue === "confirm") {
                compressPDF(photo, setPhoto).then((compressedFile) => {
                  if (compressedFile && compressedFile?.size > defaultMaxSize) {
                    swal(
                      "Error",
                      `Your file size is still too large. Please choose a different file.`,
                      "error"
                    ).then(() => {
                      setPhoto();
                    });
                    return;
                  }
                  setPhoto(compressedFile);
                });
              } else {
                setPhoto();
              }
            });
          } else {
            setPhoto();
          }
        });
        return;
      } else {
        swal(
          "Error",
          `The image exceeds the specified file size limit. Would you like to compress the image?`,
          "error",
          {
            buttons: {
              cancel: true,
              compress: {
                text: "Compress",
                value: "compress",
              },
            },
          }
        ).then((value) => {
          if (value === "compress") {
            if (photo?.size > defaultMaxSize * 2) {
              swal(
                "Error",
                `Your file is too large and unable to compress. Please upload a file upto ${
                  maxSizeMB * 2
                } MB.`,
                "error"
              ).then(() => {
                setPhoto();
              });
              return;
            }
            swal(
              "Confirm",
              "Compressing will reduce document size by 60%. Still want to proceed?",
              "warning",
              {
                buttons: {
                  cancel: true,
                  confirm: {
                    text: "Confirm",
                    value: "confirm",
                  },
                },
              }
            ).then((confirmValue) => {
              if (confirmValue === "confirm") {
                handleCompress(photo, setPhoto, defaultMaxSize);
              } else {
                setPhoto();
              }
            });
          } else {
            setPhoto();
          }
        });
        return;
      }
    }
  } else {
    const { maxFileSize, acceptedExtensions } = companyValidationRules;

    // Validate file extension
    const allowedExtensions = acceptedExtensions.map((ext) =>
      ext.toLowerCase()
    );
    const fileExtension = photo?.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(`.${fileExtension}`)) {
      swal(
        "Error",
        `Please upload photo with ${acceptedExtensions.join(", ")} extension`,
        "error"
      ).then(() => {
        setPhoto();
      });
      return;
    }

    // Validate file size
    const maxSizeBytes = maxFileSize && calculateExpression(maxFileSize);
    const maxSizeMB = maxSizeBytes / 1024 / 1024;
    if (photo?.size > maxSizeBytes) {
      if (photo?.type === "application/pdf") {
        swal(
          "Error",
          `Your file is too large. We are not accepting more than ${maxSizeMB} MB.`,
          "error",
          {
            buttons: {
              cancel: true,
              compress: {
                text: "Compress",
                value: "compress",
              },
            },
          }
        ).then((value) => {
          if (value === "compress") {
            if (photo?.size > maxSizeBytes * 2) {
              swal(
                "Error",
                `Your file is too large and unable to compress.`,
                "error"
              ).then(() => {
                setPhoto();
              });
              return;
            }
            swal(
              "Confirm",
              "Compressing will reduce document size by 60%. Do you want to proceed?",
              "warning",
              {
                buttons: {
                  cancel: true,
                  confirm: {
                    text: "Confirm",
                    value: "confirm",
                  },
                },
              }
            ).then(async (confirmValue) => {
              if (confirmValue === "confirm") {
                compressPDF(photo, setPhoto).then((compressedFile) => {
                  if (compressedFile && compressedFile?.size > maxSizeBytes) {
                    swal(
                      "Error",
                      `Your file size is still too large. Please choose a different file.`,
                      "error"
                    ).then(() => {
                      setPhoto();
                    });
                    return;
                  }
                  setPhoto(compressedFile);
                });
              } else {
                setPhoto();
              }
            });
          } else {
            setPhoto();
          }
        });
        return;
      } else {
        swal(
          "Error",
          `The uploaded file has surpassed the permitted size limit. Please click on the "compress" option to reduce the file size.`,
          "error",
          {
            buttons: {
              cancel: true,
              compress: {
                text: "Compress",
                value: "compress",
              },
            },
          }
        ).then((value) => {
          if (value === "compress") {
            if (photo?.size > maxSizeBytes * 2) {
              swal(
                "Error",
                `Your file is too large and unable to compress. Please upload a file upto ${
                  maxSizeMB * 2
                } MB.`,
                "error"
              ).then(() => {
                setPhoto();
              });
              return;
            }
            swal(
              "Confirm",
              "Compressing will reduce document size by 60%. Do you want to proceed?",
              "warning",
              {
                buttons: {
                  cancel: true,
                  confirm: {
                    text: "Confirm",
                    value: "confirm",
                  },
                },
              }
            ).then((confirmValue) => {
              if (confirmValue === "confirm") {
                handleCompress(photo, setPhoto, maxSizeBytes);
              } else {
                setPhoto();
              }
            });
          } else {
            setPhoto();
          }
        });
        return;
      }
    }
  }
};

//poi validation
export const panValidation = (companyAlias, pan_file, setpan_file) => {
  companyAlias === "shriram" &&
    pan_file?.type !== "application/pdf" &&
    pan_file?.type !== "image/png" &&
    pan_file?.type !== "image/xlsx" &&
    swal(
      "Error",
      "Please upload file in png, xlsx or pdf format",
      "error"
    ).then(() => {
      setpan_file();
    });
};
