import React from "react";
import { Col } from "react-bootstrap";
import _ from "lodash";

//Title Fn
export const SubTitleFn = (Theme, subtitle) => (
  <Col
    xs={12}
    sm={12}
    md={12}
    lg={12}
    xl={12}
    className=" mt-1"
    style={{ marginBottom: "-10px" }}
  >
    <p
      style={{
        color: Theme?.proposalHeader?.color
          ? Theme?.proposalHeader?.color
          : "#1a5105",
        fontSize: "16px",
        fontWeight: "600",
      }}
    >
      {subtitle}
    </p>
  </Col>
);

export const getTpInsuranceCompany = (
  watch,
  prepolicy,
  CardData,
  PrevinsurerTp,
  temp_data
) => {
  const selectedCompany = watch("tpInsuranceCompany");
  const prePolicyCompany = prepolicy?.tpInsuranceCompany;
  const cardDataCompany = CardData?.prepolicy?.tpInsuranceCompany;

  if (selectedCompany) {
    return selectedCompany;
  }

  if (prePolicyCompany) {
    return prePolicyCompany;
  }

  if (cardDataCompany) {
    return cardDataCompany;
  }

  if (!_.isEmpty(PrevinsurerTp)) {
    const userProposalCompany = temp_data?.userProposal?.tpInsuranceCompany;
    const corporateVehiclesCompany =
      temp_data?.corporateVehiclesQuoteRequest?.previousInsurer;

    const foundCompany =
      PrevinsurerTp.find(({ code, name }) =>
        [userProposalCompany, corporateVehiclesCompany].includes(name)
      ) || null;

    return foundCompany?.code;
  }

  return null;
};

export const getPreviousInsuranceCompany = (
  watch,
  prepolicy,
  CardData,
  Previnsurer,
  temp_data
) => {
  const selectedCompany = watch("previousInsuranceCompany");
  const prePolicyCompany = prepolicy?.previousInsuranceCompany;
  const cardDataCompany = CardData?.prepolicy?.previousInsuranceCompany;

  if (selectedCompany) {
    return selectedCompany;
  }

  if (prePolicyCompany) {
    return prePolicyCompany;
  }

  if (cardDataCompany) {
    return cardDataCompany;
  }

  if (!_.isEmpty(Previnsurer)) {
    const corporateVehiclesCompany =
      temp_data?.corporateVehiclesQuoteRequest?.previousInsurer;

    if (corporateVehiclesCompany && corporateVehiclesCompany !== "XYZ") {
      const foundCompany =
        Previnsurer.find(({ name }) => corporateVehiclesCompany === name) ||
        null;

      return foundCompany?.code || "";
    }

    const userProposalCompany = temp_data?.userProposal?.previousInsurer;
    const foundCompany =
      Previnsurer.find(({ name }) => userProposalCompany === name) || null;

    return foundCompany?.code || "";
  }

  return null;
};
