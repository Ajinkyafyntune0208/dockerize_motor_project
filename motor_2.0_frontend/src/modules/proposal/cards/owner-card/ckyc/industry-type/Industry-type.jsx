import { useEffect, useState } from "react";
import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";
import { useDispatch, useSelector } from "react-redux";
import { GetIndustryfields } from "modules/proposal/proposal.slice";
import { Typeahead } from "react-bootstrap-typeahead";
import _ from "lodash";
import PropTypes from "prop-types";
import styled from "styled-components";
import { Controller } from "react-hook-form";

export const IndustryType = ({
  temp_data,
  register,
  errors,
  setValue,
  control,
  watch,
}) => {
  const dispatch = useDispatch();
  const { industryFields } = useSelector((state) => state.proposal);
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  //calling the api if temp_data industryType is empty
  const watchIndustryType = watch("industryType");
    const selectedType =
      temp_data?.userProposal?.additonalData?.owner?.industryType?.map(
        ({code , value }) => {
          return {
            code: code,
            value: value,
          };
        }
      );


  useEffect(() => {
    if (companyAlias === "nic" && temp_data?.ownerTypeId === 2) {
      dispatch(GetIndustryfields({ company_alias: companyAlias }));
    }
  }, [temp_data?.ownerTypeId]);

  const formatedOptions = industryFields?.map((field) => ({
    code: field.code,
    value: field.value,
  }));
  const selectedValue = watchIndustryType || selectedType ;
  return (
    <>
      {temp_data?.ownerTypeId === 2 && companyAlias === "nic" ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4}>
          <div className="py-2 fname csip">
            <FormGroupTag mandatory>Industry Type</FormGroupTag>
            <Controller
              name={`industryType`}
              control={control}
              as={
                <StyledTypeaheadIndustryType
                  id="industry-type"
                  labelKey="value"
                  name={`industryType`}
                  defaultSelected={selectedValue}
                  options={formatedOptions || []}
                  errors={errors?.industryType}
                  placeholder="Select Industry Type"
                  isInvalid={!!errors?.industryType}
                  inputProps={{ style: { fontSize: "14px" } }}
                  ref={register}
                />
              }
            />
            {!!errors?.industryType && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.industryType?.message}
              </ErrorMsg>
            )}
          </div>
        </Col>
      ) : (
        <noscript />
      )}
    </>
  );
};
const StyledTypeaheadIndustryType = styled(Typeahead)`
  .dropdown {
    font-size: 18px;
    line-height: 25px;
    font-weight: 500;
    padding: 6px 14px;
    height: 40px;
    background-color: #ebeced;
    color: #666666;
    opacity: 1;
  }
  .rbt-menu {
    margin-top: 0px !important;
    border: 1px solid #999;
    border-radius: 0px;
    padding: 0px !important;
    box-shadow: 2px 10px 20px rgba(0, 0, 0, 0.1);
  }
  .dropdown-item {
    font-size: 14.5px;
    line-height: 20px;
    text-transform: capitalize;
    color: #4b4c51 !important;
    padding: 0.25rem 0.4rem !important;
    // padding: 0 0 0 25px;
    transition: border-color 0.15s ease-in-out;
  }
  .dropdown-item:hover {
    color: white !important;
    background: #1867d3 !important;
  }
`;
IndustryType.propTypes = {
  temp_data: PropTypes.object.isRequired,
  fields: PropTypes.array.isRequired,
  register: PropTypes.func.isRequired,
  errors: PropTypes.object.isRequired,
  setValue: PropTypes.func.isRequired,
  companyAlias: PropTypes.string.isRequired,
};
