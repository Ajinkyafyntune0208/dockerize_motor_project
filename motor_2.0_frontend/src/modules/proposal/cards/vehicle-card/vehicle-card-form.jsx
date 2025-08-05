import React from "react";
import { Row, Col, Form, Spinner } from "react-bootstrap";
import VehicleDetails from "./sub-sections/vehicle-details";
import FinancerDetails from "./sub-sections/financer-details";
import VehicleInspectionType from "./sub-sections/inspection";
import VehicleRegistrationAddress from "./sub-sections/vehicle-registration-address";
import PucDetails from "./sub-sections/puc-details";
import { Button } from "components";
import { handleIDVChange } from "./helper";
import { TypeReturn } from "modules/type";
import _ from "lodash";
import { addonarr } from "modules/proposal/form-section/proposal-logic";

export const CardForm = ({ allProps }) => {
  //Hook form
  const {
    handleSubmit,
    register,
    errors,
    control,
    watch,
    setValue,
    Controller,
  } = allProps;
  //Functions
  const { OnGridLoad, onSubmitVehicle, handleSearch } = allProps;
  //media queries
  const { lessthan376, lessthan768 } = allProps;
  //states
  const { temp_data, CardData, vehicle } = allProps;
  //List & Options
  const { category, usage, colors, financerList, Agreement, branchMaster } =
    allProps;
  //conditions
  //prettier-ignore
  const { allFieldsReadOnly, pucMandatory, PUC_EXP, idvChange,
    fields, PolicyCon, gridLoad, zd_rti_condition
  } = allProps;
  //variables
  //prettier-ignore
  const { regNo, regNo2, regNo3, VehicleRegNo, RegNo1,
    RegNo2, RegNo3, ManfVal, ManfValMax, type, Theme,
    financer_sel_opt, FinancerInputValue, AgreementTypeName,
    companyAlias, pin
  } = allProps;

  const showInspectionType =
    //is should not be enable in new business
    !temp_data?.newCar &&
    temp_data.selectedQuote?.policyType !== "Third Party" &&
    //field must be enabled through the config
    fields.includes("inspectionType") &&
    //not applicable for two wheeler under 350cc
    TypeReturn(type) !== "bike" &&
    //Applicable in case of breakin and policy type not tp
    (temp_data?.corporateVehiclesQuoteRequest?.businessType === "breakin" ||
      //Applicable when addons are unchecked in addon declaration in proposal
      !_.isEmpty(addonarr(temp_data)) ||
      // Applicable in case if ownership has changed.
      (temp_data?.corporateVehiclesQuoteRequest?.businessType !== "breakin" &&
        temp_data?.corporateVehiclesQuoteRequest?.ownershipChanged === "Y") ||
      //prevPolicyType should be tp and current policy type should be comp or od and only for liberty
      (temp_data?.corporateVehiclesQuoteRequest?.previousPolicyType ===
        "Third-party" &&
        (temp_data.corporateVehiclesQuoteRequest?.policyType ===
          "comprehensive" ||
          temp_data?.corporateVehiclesQuoteRequest?.policyType ===
            "own_damage") &&
        companyAlias === "liberty_videocon") ||
      !_.isEmpty(addonarr(temp_data)));

  const newBussiness =
    temp_data?.corporateVehiclesQuoteRequest?.businessType === "newbusiness";

  return (
    <Form autoComplete="off" onSubmit={handleSubmit(onSubmitVehicle)}>
      <Row
        style={{
          margin: lessthan768
            ? "-60px -30px 20px -30px"
            : "-60px -20px 20px -30px",
        }}
        className="p-2"
      >
        <VehicleDetails
          temp_data={temp_data}
          register={register}
          errors={errors}
          regNo={regNo}
          OnGridLoad={OnGridLoad}
          regNo2={regNo2}
          regNo3={regNo3}
          VehicleRegNo={VehicleRegNo}
          RegNo1={RegNo1}
          RegNo2={RegNo2}
          RegNo3={RegNo3}
          allFieldsReadOnly={allFieldsReadOnly}
          control={control}
          ManfVal={ManfVal}
          ManfValMax={ManfValMax}
          type={type}
          category={category}
          CardData={CardData}
          vehicle={vehicle}
          usage={usage}
          fields={fields}
          colors={colors}
        />
        {showInspectionType && (
          <>
            <VehicleInspectionType
              register={register}
              allFieldsReadOnly={allFieldsReadOnly}
              zd_rti_condition={zd_rti_condition}
              companyAlias={companyAlias}
              errors={errors}
              watch={watch}
              CardData={CardData}
              vehicle={vehicle}
              setValue={setValue}
            />
          </>
        )}

        {!newBussiness && (
          <PucDetails
            fields={fields}
            temp_data={temp_data}
            pucMandatory={pucMandatory}
            register={register}
            errors={errors}
            control={control}
            PUC_EXP={PUC_EXP}
            vehicle={vehicle}
            CardData={CardData}
            Theme={Theme}
            allFieldsReadOnly={allFieldsReadOnly}
            lessthan376={lessthan376}
            setValue={setValue}
          />
        )}

        <FinancerDetails
          lessthan376={lessthan376}
          watch={watch}
          Theme={Theme}
          handleSearch={handleSearch}
          allFieldsReadOnly={allFieldsReadOnly}
          financerList={financerList}
          temp_data={temp_data}
          Controller={Controller}
          control={control}
          register={register}
          financer_sel_opt={financer_sel_opt}
          FinancerInputValue={FinancerInputValue}
          errors={errors}
          Agreement={Agreement}
          CardData={CardData}
          vehicle={vehicle}
          AgreementTypeName={AgreementTypeName}
          fields={fields}
          companyAlias={companyAlias}
          branchMaster={branchMaster}
        />
        <VehicleRegistrationAddress
          lessthan376={lessthan376}
          allFieldsReadOnly={allFieldsReadOnly}
          watch={watch}
          Theme={Theme}
          register={register}
          errors={errors}
          pin={pin}
          CardData={CardData}
          temp_data={temp_data}
          vehicle={vehicle}
        />
        {/*-----Hidden I/P-----*/}
        <input
          type="hidden"
          ref={register}
          name="rtoLocation"
          value={temp_data?.regNo1 || temp_data?.rtoNumber}
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
            disabled={gridLoad}
            id="vehicle-submit"
            onClick={() => handleIDVChange(idvChange)}
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
          >
            <text
              style={{
                fontSize: "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {gridLoad ? (
                <text>
                  Fetching Details{" "}
                  <Spinner animation="grow" variant="light" size="sm" />
                </text>
              ) : PolicyCon ? (
                `Proceed to Policy${!lessthan376 ? " Details" : ""}`
              ) : (
                "Proceed"
              )}
            </text>
          </Button>
        </Col>
      </Row>
    </Form>
  );
};
