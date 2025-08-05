import * as yup from "yup";

export const fieldList = [
  "gstNumber",
  "maritalStatus",
  "occupation",
  "panNumber",
  "dob",
  "gender",
  "vehicleColor",
  "hypothecationCity",
  "cpaOptOut",
  "email",
  "pucNo",
  "pucExpiry",
  "representativeName",
  "cpaOptIn",
  "ncb",
  "inspectionType",
  "ckyc",
  "fileupload",
  "poi",
  "poa",
  "photo",
  "fatherName",
  "relationType",
  "organizationType",
  "industryType",
  "hazardousType",
];

// validation schema
export const yupValidate = yup.object({
  ...(import.meta.env.VITE_BROKER === "FYNTUNE" && {
    broker: yup.string().required("Broker is required"),
  }),
  company_alias: yup
    .string()
    .required("IC is Required")
    .matches(/[^@]/, "IC is Required"),
  section: yup
    .string()
    .required("section is Required")
    .matches(/[^@]/, "section is Required"),
  owner_type: yup
    .string()
    .required("Owner Type is Required")
    .matches(/[^@]/, "Owner Type is Required"),
});
