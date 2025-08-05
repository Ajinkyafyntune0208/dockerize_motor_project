import React, { useMemo } from "react";
import { Form, Col } from "react-bootstrap";
import styled from "styled-components";
import { useForm } from "react-hook-form";
import _ from "lodash";
import { useDispatch, useSelector } from "react-redux";
import { SaveAddonsData, SetaddonsAndOthers } from "../quote.slice";

const StyledSelect = styled(Form.Control)`
  height: calc(0.5em + 0.75rem + 2px);
  width: 100%;
  border-radius: 4px;
  font-size: 10px;
`;

const ItemName = styled.p`
  font-size: ${["BAJAJ", "ACE", "PINC", "SRIDHAR"].includes(
    import.meta.env.VITE_BROKER
  )
    ? "11px"
    : "12px"};
  text-align: left;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  white-space: nowrap;
  color: #6c757d !important;
  font-weight: ${({ theme }) => theme.regularFont?.fontWeight || "600"};

  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    font-size: 10px !important;
  }
`;

const ItemPrice = styled.p`
  text-align: end;
  font-weight: 600;
  font-size: ${["BAJAJ", "ACE", "PINC", "SRIDHAR"].includes(
    import.meta.env.VITE_BROKER
  )
    ? "11px"
    : "12px"};
  margin-right: 5px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  height: 18px !important;
  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    font-size: 10px !important;
  }
`;

const PayAsYouDrive = ({
  payD,
  FetchQuotes,
  quote,
  type,
  noDisplay,
  isTowing,
  enquiry_id,
  addOnsAndOthers,
  noPadding,
  lessthan767,
  multiUpdateQuotes,
  temp_data,
}) => {
  const dispatch = useDispatch();
  const { quoteComprehesive } = useSelector((state) => state.quotes);
  const { theme_conf } = useSelector((state) => state.home);
  const { register, watch } = useForm();

  //Fetching Addons
  const addonStructure = addOnsAndOthers?.dbStructure?.addonData?.addons
    ? addOnsAndOthers?.dbStructure?.addonData?.addons
    : [];

  //Applicable for PAYD, Towing or ZD Claims
  const applicableFor = !_.isEmpty(payD)
    ? isTowing
      ? "towing"
      : "payd"
    : "claims";

  //Is ZD Claims AVailable.
  const zdClaims = quote?.claimsCovered;
  const zdOptions = ["ONE", "TWO", "UNLIMITED"];
  const defaultClaim = theme_conf?.broker_config?.godigit_claim_covered;
  const distance = watch("distance");
  const selectedLimit = zdClaims
    ? quote?.distance
    : isTowing
    ? quote?.distance
    : !_.isEmpty(payD) &&
      distance &&
      payD?.filter((i) => i?.maxKMRange === distance * 1)[0];

  const selectedTowingSI = addonStructure.filter(
    (i) => i?.name !== "Additional Towing"
  )?.[0]?.sumInsured;

  //Fetching quote for selected driving limit
  useMemo(() => {
    if (
      distance &&
      selectedLimit &&
      (!selectedLimit?.isOptedByCustomer ||
        selectedLimit?.isOptedByCustomer === "false") &&
      (quote?.distance * 1 !== distance * 1 ||
        (addOnsAndOthers.selectedAddons.includes("additionalTowing") &&
          distance &&
          !selectedTowingSI)) &&
      !zdClaims
    ) {
      let filteredAddons = _.compact(
        addonStructure.map((i) => (i?.name !== "Additional Towing" ? i : false))
      );
      let data = {
        addonData: {
          addons: [
            ...filteredAddons,
            ...(applicableFor === "towing" ? [{
              name: "Additional Towing",

              sumInsured: distance,
              claimCovered: distance,
            }] : []),
          ],
        },
        enquiryId: enquiry_id,
      };
      //dispatch selected addon
      dispatch(
        SetaddonsAndOthers({
          ...addOnsAndOthers,
          dbStructure: data,
        })
      );

      dispatch(SaveAddonsData(data));
      FetchQuotes(
        [
          {
            policyId: quote?.policyId,
            ...(quote?.addons && {
              ...(quote?.addons && { addons: quote?.addons }),
            }),
            ...(quote?.isRenewal === "Y" && {
              is_renewal: "Y",
            }),
            companyAlias: quote?.companyAlias,
            companyId: quote?.companyId,
            distance,
            commission: quote?.commission
          },
        ],
        "comprehensive",
        quoteComprehesive,
        distance,
        isTowing
      );
    }
  }, [distance]);

  //Fetch selected Zd Option
  useMemo(() => {
    if (
      distance &&
      (distance !== quote?.claimsCovered || distance === defaultClaim) &&
      (addOnsAndOthers.selectedAddons || []).includes("zeroDepreciation") &&
      (quote?.addOnsData?.additional?.zeroDepreciation * 1 ||
        quote?.addOnsData?.inBuilt?.zeroDepreciation * 1 ||
        quote?.addOnsData?.inBuilt?.zeroDepreciation * 1 === 0)
    ) {
      let filteredAddons = _.compact(
        addonStructure.map((i) => (i?.name !== "Zero Depreciation" ? i : false))
      );
      let data = {
        addonData: {
          addons: [
            ...filteredAddons,
            ...(applicableFor === "claims" ? [{
              name: "Zero Depreciation",
              sumInsured: distance,
              claimCovered: distance,
            }] : []),
          ],
        },
        enquiryId: enquiry_id,
      };
      //dispatch selected addon
      dispatch(
        SetaddonsAndOthers({
          ...addOnsAndOthers,
          dbStructure: data,
        })
      );
      dispatch(SaveAddonsData(data));
      //Get all policies for zd claims.
      let policies = !_.isEmpty(multiUpdateQuotes)
        ? multiUpdateQuotes
            .filter((i) => i.companyAlias === "godigit" && i.claimsCovered)
            .map((x) => ({
              policyId: x.policyId,
              ...(x?.addons && {
                ...(x?.addons && { addons: x?.addons }),
              }),
              ...(x?.isRenewal === "Y" && {
                is_renewal: "Y",
              }),
              companyAlias: x?.companyAlias,
              companyId: x?.companyId,
              distance,
              commission: quote?.commission
            }))
        : [];
      //Fetch ZD claims products for the filtered policy Ids.
      FetchQuotes(
        policies,
        "comprehensive",
        quoteComprehesive,
        distance,
        true,
        true
      );
    }
  }, [distance]);

  //Render Options
  const _renderOptions = () => {
    if (zdClaims) {
      return zdOptions.map((v) => (
        <option
          selected={
            temp_data?.selectedQuote?.claimsCovered === v ||
            quote?.claimsCovered === v
          }
          value={v}
        >
          {v}
        </option>
      ));
    } else if (isTowing) {
      return payD.map((v) => (
        <option selected={quote?.distance * 1 === v * 1} value={v}>
          {`${v} ${quote?.companyAlias === "oriental" ? `₹` : `Kms`}`}
        </option>
      ));
    } else {
      return payD?.map((v) => (
        <option
          selected={v?.isOptedByCustomer && v?.isOptedByCustomer !== "false"}
          value={v?.maxKMRange}
        >{`${parseInt(v?.maxKMRange)} km/yr`}</option>
      ));
    }
  };

  return (
    <>
      <Col
        sm={7}
        md={7}
        lg={7}
        xl={7}
        style={{ ...(noDisplay && { visibility: "hidden" }) }}
        className={noPadding && lessthan767 ? "p-0" : ""}
      >
        <ItemName style={{ paddingInline: lessthan767 ? "7px 0px" : "" }}>
          {zdClaims
            ? "No. of claims"
            : isTowing
            ? "Towing Limit"
            : "Annual driving limit"}
        </ItemName>
      </Col>
      <Col
        sm={5}
        md={5}
        lg={5}
        xl={5}
        style={{ ...(noDisplay && { visibility: "hidden" }) }}
        className={noPadding && lessthan767 ? "p-0" : ""}
      >
        <ItemPrice>
          <StyledSelect
            as="select"
            ref={register}
            name="distance"
            style={{ marginRight: lessthan767 ? "-21px" : "" }}
          >
            {_renderOptions()}
          </StyledSelect>
        </ItemPrice>
      </Col>
    </>
  );
};

export default PayAsYouDrive;
