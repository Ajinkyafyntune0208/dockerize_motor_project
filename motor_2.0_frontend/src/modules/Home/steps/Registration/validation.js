import * as yup from "yup";
// validation schema
export const yupValidate = yup.object({
  regNo1: yup
    .string()
    .matches(/^[A-Z]{2}[-][0-9\s]{1,2}$/, "Invalid RTO Number")
    .required("Registration No. is required"),
  regNo2: yup
    .string()
    .matches(/^[A-Za-z\s]{1,3}$/, "Invalid Number")
    .nullable()
    .transform((v, o) => (o === "" ? null : v)),
  regNo3: yup
    .string()
    .required("Number is required")
    .matches(/^[0-9]{4}$/, "Invalid Number"),
});
