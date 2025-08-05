import React, { useState } from "react";
import { Button } from "components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { Row, Col, Form } from "react-bootstrap";
import { useForm } from "react-hook-form";
import _ from "lodash";
import { numOnly, toDate, _haptics } from "utils";
import { useSelector, useDispatch } from "react-redux";
import { differenceInDays } from "date-fns";
import moment from "moment";
import styled from "styled-components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { policyValidation } from "../../form-section/validation";
import PreviousInsurerInputs from "./inputs/prev-ins-details";
import TpDetails from "./inputs/tp-details";
import CpaDetails from "./inputs/cpa-details";
// prettier-ignore
import { useFastLanePrefill, useLoadPreviousIcList, usePrefillPolicyCard, useResetCpaDetails, useSetCpaInsurerCompany, 
  useSetCpaReason, useSetPreviousInsurerCompany, useSetPreviousPolicyExpiryDate, useSetPreviousPolicyStartDate, 
  useSetTpInsurerCompanyName, useSetTpInsurerNumber, useSetTpStartDate, useTpPolicyEndDateCalculation,
} from "./policy-card-hook";
import {
  getPreviousInsuranceCompany,
  getTpInsuranceCompany,
} from "./helper";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const PolicyCard = ({
  onSubmitPrepolicy,
  prepolicy,
  CardData,
  prevPolicyCon,
  PACon,
  enquiry_id,
  Theme,
  OwnDamage,
  type,
  lessthan768,
  isNcbApplicable,
  TypeReturn,
  fields,
  PolicyValidationExculsion,
  theme_conf,
  isEditable,
}) => {
  const [reasonCpa, setReasonCpa] = useState("");
  /*----------------Validation Schema---------------------*/
  const { temp_data, prevIc, prevIcTp } = useSelector(
    (state) => state.proposal
  );

  //prettier-ignore
  const yupValidate = yup.object({
    ...policyValidation(
      temp_data, prevPolicyCon, PolicyValidationExculsion,
      OwnDamage, PACon, reasonCpa
    ),
  });
  /*----------x------Validation Schema----------x-----------*/
  const dispatch = useDispatch();
  const expiryDate = temp_data?.corporateVehiclesQuoteRequest
    ?.previousPolicyExpiryDate
    ? temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate
    : "";
  const startDate = temp_data?.userProposal?.prevPolicyStartDate
    ? temp_data?.userProposal?.prevPolicyStartDate
    : "";

  const {
    handleSubmit,
    register,
    errors,
    control,
    reset,
    watch,
    setValue,
    trigger,
  } = useForm({
    defaultValues: !_.isEmpty(prepolicy)
      ? prepolicy
      : !_.isEmpty(CardData?.prepolicy)
      ? CardData?.prepolicy.prevPolicyExpiryDate === expiryDate && expiryDate
        ? CardData?.prepolicy
        : { ...CardData?.prepolicy, prevPolicyExpiryDate: expiryDate }
      : {},
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  const allFieldsReadOnly = temp_data?.selectedQuote?.isRenewal === "Y" && !isEditable;
  // || temp_data?.corporateVehiclesQuoteRequest?.rolloverRenewal === "Y";

  //Setting Policy Expiry Date
  useSetPreviousPolicyExpiryDate(expiryDate, setValue);

  //prefill Api hook
  usePrefillPolicyCard(prepolicy, CardData, reset, expiryDate);

  //fixed values
  const companyAlias = !_.isEmpty(temp_data?.selectedQuote)
    ? temp_data?.selectedQuote?.companyAlias
    : "";

  //Load Previous IC List
  // prettier-ignore
  useLoadPreviousIcList(companyAlias, prevPolicyCon, fields, enquiry_id, OwnDamage);

  const Previnsurer = !_.isEmpty(prevIc)
    ? prevIc?.map(({ name, code }) => {
        return { name, code };
      })
    : [];

  const PrevinsurerTp = !_.isEmpty(prevIcTp)
    ? prevIcTp?.map(({ name, code }) => {
        return { name, code };
      })
    : [];

  //filter name of prev IC
  // prettier-ignore
  const previousInsuranceCompany = getPreviousInsuranceCompany(watch, prepolicy, CardData, Previnsurer, temp_data);

  const IcName = Previnsurer?.filter(
    ({ code }) => previousInsuranceCompany === code
  );

  const ODlastYr =
    differenceInDays(
      toDate(moment().format("DD-MM-YYYY")),
      toDate(temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate) <=
        365
    ) || "";

  useSetPreviousInsurerCompany(IcName, setValue, type, ODlastYr, watch);

  /*-----CPA Details-----*/
  //trigger addon save
  const ReasonIP = watch("reason");
  // prettier-ignore
  useSetCpaReason(ReasonIP, PACon, setReasonCpa, enquiry_id, temp_data, dispatch);

  const CpaInsuranceCompany =
    watch("cPAInsComp") ||
    prepolicy?.cPAInsComp ||
    CardData?.prepolicy?.cPAInsComp;

  const CpaIcName = Previnsurer?.filter(
    ({ code }) => CpaInsuranceCompany === code
  );

  useSetCpaInsurerCompany(CpaIcName, setValue);

  //Names for Summary
  const cpaPolicyNo = watch("cPAPolicyNo");
  const CpaFmDate = watch("cPAPolicyFmDt");
  const CpaToDate = watch("cPAPolicyToDt");
  const CpaSumIns = watch("cPASumInsured");

  //resetting cpa details
  useResetCpaDetails(prepolicy, CardData, reasonCpa, setValue);

  /*--x--CPA Details--x--*/

  /*-----TP Details-----*/

  // filter name of prev tp IC
  // prettier-ignore
  const TpProposalPrefill = getTpInsuranceCompany(watch, prepolicy, CardData, PrevinsurerTp, temp_data) || "";

  const TpInsuranceCompany =
    TpProposalPrefill || "" ? TpProposalPrefill || "" : null;

  const TpIcName = PrevinsurerTp?.filter(
    ({ code }) => TpInsuranceCompany === code
  );

  useSetTpInsurerCompanyName(TpIcName, setValue);

  //TP Policy End Date Calculation  i.e  3yr - 1d for Bike & 5yr - 1d for car
  const TPStartDate = watch("tpStartDate");
  useTpPolicyEndDateCalculation(TPStartDate, temp_data, type, setValue);

  /*--x--TP Details--x--*/

  //tp start date prefill
  useSetTpStartDate(temp_data, CardData, TPStartDate, setValue);

  //fastLane prefill
  useFastLanePrefill(CardData, temp_data, setValue);

  //variables for hidden i/p ( summary header changes)
  const previousPolicyExpiry = watch("prevPolicyExpiryDate");

  //previous policy number condition for first renewal
  const previousPolicyNumber = watch("previousPolicyNumber");

  // prefill tp insurer number
  // prettier-ignore
  useSetTpInsurerNumber(temp_data, PolicyValidationExculsion, setValue, previousPolicyNumber);

  // prefill previous Policy Start Date
  useSetPreviousPolicyStartDate(temp_data, startDate, setValue);

  //disable submit button
  const generalCon =
    prevPolicyCon && _.isEmpty(IcName) && !PolicyValidationExculsion;
    
  const SubmitCon =
    ((prevPolicyCon || OwnDamage) &&
      (_.isEmpty(Previnsurer) ||
        generalCon ||
        (OwnDamage && _.isEmpty(TpIcName))));

  return (
    <Form onSubmit={handleSubmit(onSubmitPrepolicy)} autoComplete="off">
      <Row
        style={{
          margin: lessthan768
            ? "-60px -30px 20px -30px"
            : "-60px -20px 20px -30px",
        }}
        className="p-2"
      >
        {prevPolicyCon && !PolicyValidationExculsion ? (
          <PreviousInsurerInputs
            allFieldsReadOnly={allFieldsReadOnly}
            register={register}
            errors={errors}
            Previnsurer={Previnsurer}
            CardData={CardData}
            temp_data={temp_data}
            control={control}
            previousPolicyExpiry={previousPolicyExpiry}
            isNcbApplicable={isNcbApplicable}
            fields={fields}
            prepolicy={prepolicy}
            watch={watch}
          />
        ) : (
          <noscript />
        )}
        {prevPolicyCon && OwnDamage && (
          <TpDetails
            PolicyValidationExculsion={PolicyValidationExculsion}
            Theme={Theme}
            temp_data={temp_data}
            register={register}
            watch={watch}
            allFieldsReadOnly={allFieldsReadOnly}
            errors={errors}
            CardData={CardData}
            PrevinsurerTp={PrevinsurerTp}
            control={control}
            ODlastYr={ODlastYr}
            TPStartDate={TPStartDate}
            prepolicy={prepolicy}
          />
        )}
        {PACon ? (
          <CpaDetails
            prevPolicyCon={prevPolicyCon}
            Theme={Theme}
            PACon={PACon}
            lessthan768={lessthan768}
            register={register}
            allFieldsReadOnly={allFieldsReadOnly}
            theme_conf={theme_conf}
            CardData={CardData}
            watch={watch}
            errors={errors}
            Previnsurer={Previnsurer}
            control={control}
            CpaFmDate={CpaFmDate}
            prepolicy={prepolicy}
            CpaToDate={CpaToDate}
            numOnly={numOnly}
            reasonCpa={reasonCpa}
            cpaPolicyNo={cpaPolicyNo}
            CpaSumIns={CpaSumIns}
          />
        ) : (
          <>
            {/*resetting CPA values to update DB*/}
            <input type="hidden" value={null} name={"cPAInsComp"} />
            <input type="hidden" value={null} name={"cPAPolicyNo"} />
            <input type="hidden" value={null} name={"cPAPolicyFmDt"} />
            <input type="hidden" value={null} name={"cPAPolicyToDt"} />
            <input type="hidden" value={null} name={"cPASumInsured"} />
          </>
        )}
        <Col
          sm={12}
          lg={12}
          md={12}
          xl={12}
          className="d-flex justify-content-center mt-5"
          //triggering input validation manually
          onClick={() => [
            trigger("previousInsuranceCompany"),
            trigger("tpInsuranceCompany"),
          ]}
        >
          <Button
            type="submit"
            buttonStyle="outline-solid"
            disabled={SubmitCon || ""}
            id="policy-submit"
            hex1={
              Theme?.proposalProceedBtn?.hex1
                ? Theme?.proposalProceedBtn?.hex1
                : "#4ca729"
            }
            hex2={
              Theme?.proposalProceedBtn?.hex2
                ? Theme?.proposalProceedBtn?.hex2
                : "#4ca729"
            }
            borderRadius="5px"
            color="white"
            shadow={"none"}
            onClick={() => _haptics([100, 0, 50])}
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {"Proceed"}
            </text>
          </Button>
        </Col>
      </Row>
    </Form>
  );
};

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

export default PolicyCard;
