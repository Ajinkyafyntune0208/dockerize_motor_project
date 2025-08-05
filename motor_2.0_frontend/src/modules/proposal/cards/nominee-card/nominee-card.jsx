import React, { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { Form, Row, Col } from "react-bootstrap";
import { Button } from "../../../../components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm, Controller } from "react-hook-form";
import _ from "lodash";
import { getRelation } from "../../proposal.slice";
import { SaveAddonsData } from "modules/quotesPage/quote.slice";
import { NomineeDetails } from "./nominee-details";
import { nomineeValidation } from '../../form-section/validation'
import { _haptics } from 'utils';

const NomineeCard = ({
  onSubmitNominee,
  nominee,
  CardData,
  Theme,
  lessthan768,
  lessthan376,
  PACondition,
  enquiry_id,
  dropout,
  NomineeBroker,
  type,
  Tenure,
  fields,
}) => {
  const dispatch = useDispatch();
  const { relation, temp_data } = useSelector((state) => state.proposal);
  const { cpaSet } = useSelector((state) => state.quotes);
  const [cpaToggle, setCpaToggle] = useState(false);
  /*----------------Validation Schema---------------------*/
  const yupValidate = yup.object(
    {...nomineeValidation(temp_data, fields, cpaToggle, NomineeBroker)}
  );
  /*----------x------Validation Schema----------x-----------*/

  const { handleSubmit, register, errors, control, reset, watch, setValue } =
    useForm({
      defaultValues: !_.isEmpty(nominee)
        ? nominee
        : !_.isEmpty(CardData?.nominee)
        ? CardData?.nominee
        : {},
      resolver: yupResolver(yupValidate),
      mode: "onBlur",
      reValidateMode: "onBlur",
    });

  //prefill Api
  useEffect(() => {
    if (_.isEmpty(nominee) && !_.isEmpty(CardData?.nominee)) {
      reset(CardData?.nominee);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData.nominee]);

  const companyAlias = !_.isEmpty(temp_data?.selectedQuote)
    ? temp_data?.selectedQuote?.companyAlias
    : "";

  //get nominee data
  useEffect(() => {
    dispatch(
      getRelation({ companyAlias: companyAlias, enquiryId: enquiry_id })
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyAlias]);

  //prefill
  useEffect(() => {
    if (_.isEmpty(CardData?.nominee) && _.isEmpty(nominee)) {
      temp_data?.userProposal?.nomineeName &&
        setValue("nomineeName", temp_data?.userProposal?.nomineeName);
      temp_data?.userProposal?.nomineeDob &&
        setValue("nomineeDob", temp_data?.userProposal?.nomineeDob);
      temp_data?.userProposal?.nomineeRelationship &&
        setValue(
          "nomineeRelationship",
          temp_data?.userProposal?.nomineeRelationship
        );
    }
  }, [temp_data?.userProposal]);

  //setting cpa
  const cpaStatus = watch("cpa")
    ? watch("cpa")
    : !_.isEmpty(temp_data) && !PACondition
    ? !_.isEmpty(Tenure)
      ? "MultiYear"
      : "OneYear"
    : "NO";

  //if cpaStatus is positive and field value for cpa is null
  useEffect(() => {
    if (
      cpaStatus &&
      !watch("cpa") &&
      !_.isEmpty(temp_data) &&
      fields.includes("cpaOptIn")
    ) {
      setValue("cpa", cpaStatus);
    }
  }, [cpaStatus, temp_data]);

  useEffect(() => {
    if (
      cpaStatus &&
      !_.isEmpty(temp_data) &&
      !dropout &&
      temp_data?.corporateVehiclesQuoteRequest?.policyType !== "own_damage" &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C" &&
      fields.includes("cpaOptIn")
    ) {
      if (cpaStatus === "MultiYear") {
        let data1 = {
          isProposal: true,
          lastProposalModifiedTime: temp_data?.lastProposalModifiedTime,
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          addonData: {
            compulsory_personal_accident: [
              {
                name: "Compulsory Personal Accident",
                tenure: type === "bike" ? 5 : 3,
              },
            ],
          },
        };

        dispatch(SaveAddonsData(data1, true));
        setCpaToggle(true);
      }
      if (cpaStatus === "OneYear") {
        let data1 = {
          isProposal: true,
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          addonData: {
            compulsory_personal_accident: [
              { name: "Compulsory Personal Accident" },
            ],
          },
        };

        dispatch(SaveAddonsData(data1, true));
        setCpaToggle(true);
      }
      if (cpaStatus === "NO") {
        let data1 = {
          isProposal: true,
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          addonData: {
            compulsory_personal_accident: [
              {
                reason:
                  "I have another motor policy with PA owner driver cover in my name",
              },
            ],
          },
        };
        dispatch(SaveAddonsData(data1, true));
        setCpaToggle(false);
      }
    }
  }, [cpaStatus]);

  //prefill cpa for the first time
  useEffect(() => {
    if (
      !_.isEmpty(temp_data) &&
      _.isEmpty(CardData?.nominee) &&
      _.isEmpty(nominee) &&
      PACondition &&
      fields.includes("cpaOptIn") &&
      !watch("cpa")
    ) {
      setValue("cpa", "NO");
    }
  }, [temp_data]);

  return (
    <Form onSubmit={handleSubmit(onSubmitNominee)} autoComplete="none">
      <Row
        style={{
          margin: lessthan768
            ? "-60px -30px 20px -30px"
            : "-60px -20px 20px -30px",
        }}
        className="p-2"
      >
        {/* Nominee fiels in this component */}
        <NomineeDetails
          temp_data={temp_data}
          fields={fields}
          Controller={Controller}
          control={control}
          PACondition={PACondition}
          Tenure={Tenure}
          type={type}
          register={register}
          watch={watch}
          cpaStatus={cpaStatus}
          NomineeBroker={NomineeBroker}
          nominee={nominee}
          CardData={CardData}
          errors={errors}
          relation={relation}
        />
        <Col
          sm={12}
          lg={12}
          md={12}
          xl={12}
          className="d-flex justify-content-center mt-5"
        >
          <Button
            type="submit"
            buttonStyle="outline-solid"
            id="nominee-submit"
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
            disabled={cpaSet}
            onClick={() =>
              _haptics([100, 0, 50])
            }
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {`Proceed to Vehicle${!lessthan376 ? " Details" : ""}`}
            </text>
          </Button>
        </Col>
      </Row>
    </Form>
  );
};

export default NomineeCard;
